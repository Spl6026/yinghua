<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\FavoritePage;
use Yinghua\DTO\FavoriteStatusInput;
use Yinghua\DTO\FavoriteWriteResult;
use Yinghua\DTO\MemberPageInput;
use Yinghua\Models\Account;
use Yinghua\Repositories\FavoriteRepository;

/** [S：E13／G15；E-27] 明確收藏／取消，不用重送會反轉狀態的 toggle。 */
class MemberFavorites
{
    public function __construct(private readonly FavoriteRepository $favorites, private readonly MemberAccess $access, private readonly MemberAttractions $attractions, private readonly Transactions $transactions) {}

    public function listing(Account $actor, MemberPageInput $query): FavoritePage
    {
        $position = $query->favoritePosition();

        return $this->transactions->run(function () use ($actor, $query, $position): FavoritePage {
            $member = $this->access->require($actor);
            $records = $this->favorites->page($member->id, $query->pageSize + 1, $position);
            $more = count($records) > $query->pageSize;
            $records = array_slice($records, 0, $query->pageSize);
            $snapshot = $this->attractions->snapshot();
            $items = [];
            foreach ($records as $record) {
                $items[] = ['attractionId' => $record->attractionId, 'savedAt' => str_replace(' ', 'T', $record->createdAt) . '+00:00', ...$snapshot->view($record->attractionId)];
            }
            $last = $records === [] ? null : $records[array_key_last($records)];

            return new FavoritePage($items, $more && $last !== null ? MemberPageInput::cursor(['at' => $last->createdAt, 'id' => $last->attractionId]) : null);
        });
    }

    /** @return list<array{attractionId:string,favorited:bool}> */
    public function status(Account $actor, FavoriteStatusInput $input): array
    {
        $member = $this->access->require($actor);
        $saved = array_fill_keys($this->favorites->status($member->id, $input->ids), true);

        return array_map(static fn(string $id): array => ['attractionId' => $id, 'favorited' => isset($saved[$id])], $input->ids);
    }

    public function add(Account $actor, string $attractionId): FavoriteWriteResult
    {
        return $this->transactions->run(function () use ($actor, $attractionId): FavoriteWriteResult {
            $member = $this->access->require($actor, true);
            if ($this->favorites->exists($member->id, $attractionId)) {
                return new FavoriteWriteResult($attractionId, false);
            }
            $this->attractions->requireAvailable($attractionId);
            $this->favorites->add($member->id, $attractionId);

            return new FavoriteWriteResult($attractionId, true);
        });
    }

    public function remove(Account $actor, string $attractionId): void
    {
        $this->transactions->run(function () use ($actor, $attractionId): void {
            $member = $this->access->require($actor, true);
            $this->favorites->remove($member->id, $attractionId);
        });
    }
}
