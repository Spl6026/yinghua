<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\CurrentItineraryPage;
use Yinghua\DTO\ItineraryItemInput;
use Yinghua\DTO\ItineraryWriteResult;
use Yinghua\DTO\MemberPageInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Repositories\ItineraryRepository;
use Yinghua\Support\Secrets;
use Yinghua\Exceptions\ErrorCode;

/** 會員的「目前行程」視為一個整體處理；先鎖住行程主表並比對版本，避免多個分頁同時編輯互相覆蓋。 */
class CurrentItinerary
{
    public function __construct(
        private readonly ItineraryRepository $itineraries,
        private readonly MemberAccess $access,
        private readonly MemberAttractions $attractions,
        private readonly Transactions $transactions,
    ) {}

    public function listing(Account $actor, MemberPageInput $query): CurrentItineraryPage
    {
        $position = $query->itineraryPosition();

        return $this->transactions->run(function () use ($actor, $query, $position): CurrentItineraryPage {
            $member = $this->access->require($actor);
            $itinerary = $this->itineraries->current($member->id);
            if ($position !== null
                && ($itinerary === null || $position['id'] !== $itinerary->id || $position['version'] !== $itinerary->version)) {
                throw new ApplicationError(ErrorCode::Conflict, '行程已變更，請重新載入後繼續。');
            }
            if ($itinerary === null) {
                return new CurrentItineraryPage(null, [], null);
            }
            $records = $this->itineraries->items($member->id, $itinerary->id, $query->pageSize + 1, $position['order'] ?? 0);
            $more = count($records) > $query->pageSize;
            $records = array_slice($records, 0, $query->pageSize);
            $snapshot = $this->attractions->snapshot();
            $items = [];
            foreach ($records as $record) {
                $items[] = ['attractionId' => $record->attractionId,
                    'sortOrder' => $record->sortOrder,
                    'plannedTime' => $record->plannedTime,
                    'note' => $record->note,
                    'snapshotName' => $record->snapshotName,
                    ...$snapshot->view($record->attractionId)];
            }
            $last = $records === [] ? null : $records[array_key_last($records)];

            return new CurrentItineraryPage($itinerary, $items, $more && $last !== null
                ? MemberPageInput::cursor(['id' => $itinerary->id, 'version' => $itinerary->version, 'order' => $last->sortOrder])
                : null);
        });
    }

    public function add(Account $actor, string $attractionId, ItineraryItemInput $input): ItineraryWriteResult
    {
        return $this->transactions->run(function () use ($actor, $attractionId, $input): ItineraryWriteResult {
            $member = $this->access->require($actor, true);
            $before = $this->itineraries->current($member->id, true);
            if ($before !== null && $this->itineraries->item($member->id, $before->id, $attractionId) !== null) {
                return new ItineraryWriteResult($before->id, $before->version, $attractionId, false);
            }
            if (($before->version ?? 0) !== $input->version || !self::sameTrip($before?->id, $input)) {
                throw new ApplicationError(ErrorCode::Conflict, '行程已變更，請重新載入再加入。');
            }
            $name = $this->attractions->requireAvailable($attractionId);
            $current = $before ?? $this->itineraries->createCurrent($member->id, Secrets::uuid());
            $this->itineraries->append($member->id, $current->id, Secrets::uuid(), $attractionId, $name);
            $after = $before === null ? $current : $this->itineraries->advance($member->id, $current->id);

            return new ItineraryWriteResult($after->id, $after->version, $attractionId, true);
        });
    }

    public function remove(Account $actor, string $attractionId, ItineraryItemInput $input): ItineraryWriteResult
    {
        return $this->transactions->run(function () use ($actor, $attractionId, $input): ItineraryWriteResult {
            $member = $this->access->require($actor, true);
            $before = $this->itineraries->current($member->id, true);
            if ($before === null || $this->itineraries->item($member->id, $before->id, $attractionId) === null) {
                return new ItineraryWriteResult($before?->id, $before->version ?? 0, $attractionId, false);
            }
            if ($before->version !== $input->version || !self::sameTrip($before->id, $input)) {
                throw new ApplicationError(ErrorCode::Conflict, '行程已變更，請重新載入再移除。');
            }
            $this->itineraries->remove($member->id, $before->id, $attractionId);
            $after = $this->itineraries->advance($member->id, $before->id);

            return new ItineraryWriteResult($after->id, $after->version, $attractionId, true);
        });
    }

    /** 必須仍是同一份目前行程（version=0 代表還沒有行程，不需編號）；版本號只在單一行程內遞增，不能單靠版本判斷。 */
    private static function sameTrip(?string $currentId, ItineraryItemInput $input): bool
    {
        return $input->itineraryId === null || $input->itineraryId === $currentId;
    }
}
