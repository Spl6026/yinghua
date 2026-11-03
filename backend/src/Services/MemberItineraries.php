<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\{ContentFields, ItineraryStop, MemberItinerary, TripCreated, TripInput, TripView, PublicCollection};
use Yinghua\Exceptions\{ApplicationError, ContentConflict};
use Yinghua\Models\Account;
use Yinghua\Policies\RecommendedRouteSelection;
use Yinghua\Repositories\{ItineraryRepository, RecommendedRouteRepository};
use Yinghua\Support\Secrets;
use Yinghua\Exceptions\ErrorCode;

/** 會員本人的多份行程：每次儲存都在交易內鎖定會員、比對版本，並整份覆寫行程內容。 */
class MemberItineraries
{
    public function __construct(
        private readonly ItineraryRepository $records,
        private readonly MemberAccess $access,
        private readonly MemberAttractions $attractions,
        private readonly Transactions $transactions,
        private readonly ContentPublication $publication,
        private readonly RecommendedRouteRepository $routes,
    ) {}

    /** @return array<string,mixed> */
    public function listing(Account $actor, int $page, int $size): array
    {
        return $this->transactions->run(function () use ($actor, $page, $size): array {
            $member = $this->access->require($actor);
            $data = $this->records->collection($member->id, $page, $size);
            $current = $this->records->current($member->id)?->id;
            return ['items' => array_map(static fn(MemberItinerary $trip): array => [...$trip->toArray(),
                'isCurrent' => $trip->id === $current], $data['items']),
                'total' => $data['total'],
                'page' => $page,
                'pageSize' => $size,
                'currentId' => $current];
        });
    }

    public function get(Account $actor, string $id): TripView
    {
        return $this->transactions->run(function () use ($actor, $id): TripView {
            $member = $this->access->require($actor);
            return $this->view($member->id, $this->target($member->id, $id));
        });
    }

    public function create(Account $actor, TripInput $input): TripCreated
    {
        try {
            return $this->transactions->run(function () use ($actor, $input): TripCreated {
                $member = $this->access->require($actor, true);
                $id = $input->id ?? Secrets::uuid();
                $before = $this->records->findOwned($member->id, $id, true);
                if ($before !== null) {
                    return new TripCreated($this->view($member->id, $before), false);
                }
                $items = $this->prepareItems($member->id, null, $input);
                $trip = $this->records->createOwned($member->id, $id, $input->title, $input->tripDate);
                if ($items !== []) {
                    $trip = $this->records->saveOwned($member->id, $id, $input->title, $input->tripDate, $items);
                }
                return new TripCreated($this->view($member->id, $trip), true);
            });
        } catch (ContentConflict) {
            throw new ApplicationError(ErrorCode::Conflict, '此行程識別無法使用，請重新載入。');
        }
    }

    public function save(Account $actor, string $id, TripInput $input): TripView
    {
        return $this->transactions->run(function () use ($actor, $id, $input): TripView {
            $member = $this->access->require($actor, true);
            $before = $this->target($member->id, $id, true);
            $this->version($before, $input->version);
            $items = $this->prepareItems($member->id, $before, $input);
            return $this->view($member->id, $this->records->saveOwned($member->id, $id, $input->title, $input->tripDate, $items));
        });
    }

    public function select(Account $actor, string $id, int $version): TripView
    {
        return $this->transactions->run(function () use ($actor, $id, $version): TripView {
            $member = $this->access->require($actor, true);
            $before = $this->target($member->id, $id, true);
            $this->version($before, $version);
            return $this->view($member->id, $this->records->selectCurrent($member->id, $id));
        });
    }

    public function delete(Account $actor, string $id, int $version): void
    {
        $this->transactions->run(function () use ($actor, $id, $version): void {
            $member = $this->access->require($actor, true);
            $before = $this->target($member->id, $id, true);
            $this->version($before, $version);
            $this->records->deleteOwned($member->id, $id);
        });
    }

    /** 把推薦路線套用到行程，取代原本的名稱與站點；只有路線快照與目前資料一致時才整份寫入。 */
    public function apply(Account $actor, string $id, string $routeId, int $version): TripView
    {
        ContentFields::uuid($routeId, 'routeId');
        return $this->transactions->run(function () use ($actor, $id, $routeId, $version): TripView {
            $member = $this->access->require($actor, true);
            $before = $this->target($member->id, $id, true);
            $this->version($before, $version);
            $snapshot = $this->publication->read();
            $published = null;
            foreach (PublicCollection::items($snapshot, 'recommendedRoutes') as $route) {
                if (($route['id'] ?? null) === $routeId) {
                    $published = $route;
                    break;
                }
            }
            $route = $this->routes->find($routeId, true);
            if ($published === null || $route === null || $route->status !== 'published' || $route->deletedAt !== null) {
                throw new ApplicationError(ErrorCode::NotFound, '此推薦路線目前無法套用。');
            }
            $current = RecommendedRouteSelection::project([$route], PublicCollection::items($snapshot, 'items'))[0] ?? null;
            if ($current !== $published) {
                throw new ApplicationError(ErrorCode::Conflict, '推薦路線已變更，請重新載入後確認。');
            }
            $ids = [];
            foreach ($route->stops as $stop) {
                if ($stop['available']) {
                    $ids[] = $stop['attractionId'];
                }
            }
            $names = $this->attractions->requireAvailableNames($ids);
            $items = [];
            foreach ($route->stops as $stop) {
                if (isset($names[$stop['attractionId']])) {
                    $items[] = new ItineraryStop(
                        Secrets::uuid(),
                        $stop['attractionId'],
                        count($items) + 1,
                        $stop['suggestedTime'] === null ? null : $stop['suggestedTime'] . ':00',
                        $stop['note'],
                        $names[$stop['attractionId']],
                    );
                }
            }
            if ($items === []) {
                throw new ApplicationError(ErrorCode::NotFound, '此路線沒有可用站點。');
            }
            return $this->view($member->id, $this->records->saveOwned($member->id, $id, $route->title, $before->tripDate, $items));
        });
    }

    /** @return list<ItineraryStop> */
    private function prepareItems(string $memberId, ?MemberItinerary $before, TripInput $input): array
    {
        $old = [];
        if ($before !== null) {
            foreach ($this->records->allItems($memberId, $before->id) as $item) {
                $old[$item->attractionId] = $item;
            }
        }
        $new = [];
        foreach ($input->items as $item) {
            if (!isset($old[$item->attractionId])) {
                $new[] = $item->attractionId;
            }
        }
        $names = $this->attractions->requireAvailableNames($new);
        $items = [];
        foreach ($input->items as $item) {
            $existing = $old[$item->attractionId] ?? null;
            $itemId = $existing === null ? Secrets::uuid() : $existing->id;
            $name = $existing === null ? $names[$item->attractionId] : $existing->snapshotName;
            $items[] = new ItineraryStop($itemId, $item->attractionId, count($items) + 1, $item->startTime, $item->note, $name);
        }
        return $items;
    }

    private function target(string $memberId, string $id, bool $lock = false): MemberItinerary
    {
        ContentFields::uuid($id, 'id');
        return $this->records->findOwned($memberId, $id, $lock) ?? throw new ApplicationError(ErrorCode::NotFound, '找不到此行程。');
    }

    private function version(MemberItinerary $trip, int $version): void
    {
        if ($trip->version !== $version) {
            throw new ApplicationError(ErrorCode::Conflict, '行程已被其他分頁修改，請重新載入；目前輸入尚未保存。');
        }
    }

    private function view(string $memberId, MemberItinerary $trip): TripView
    {
        $snapshot = $this->attractions->snapshot();
        $items = [];
        foreach ($this->records->allItems($memberId, $trip->id) as $item) {
            $items[] = ['attractionId' => $item->attractionId,
                'sortOrder' => $item->sortOrder,
                'startTime' => $item->plannedTime === null ? null : substr($item->plannedTime, 0, 5),
                'note' => $item->note,
                'snapshotName' => $item->snapshotName,
                ...$snapshot->view($item->attractionId)];
        }
        return new TripView($trip, $this->records->current($memberId)?->id === $trip->id, $items);
    }
}
