<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\{AdminContentQuery, ClientContext, ContentFields, RecommendedRoute, RecommendedRouteInput, RouteWriteResult};
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\{Account, ContentStatus};
use Yinghua\Repositories\{EventRepository, RecommendedRouteRepository};
use Yinghua\Support\Secrets;

/** 推薦路線的後台管理：權限、交易、版本比對與發布都集中在這裡，Controller 不直接操作資料庫。 */
class RecommendedRoutes
{
    public function __construct(
        private readonly RecommendedRouteRepository $records,
        private readonly Authorization $authorization,
        private readonly Transactions $transactions,
        private readonly ContentPublication $publication,
        private readonly EventRepository $events,
    ) {}

    /** @return array<string,mixed> */
    public function listing(Account $actor, AdminContentQuery $query): array
    {
        $this->authorization->require($actor, 'routes', 'view');
        if ($query->status !== '' && ContentStatus::tryFrom($query->status) === null) {
            throw ApplicationError::invalid('status', '發布狀態無效。');
        }
        return $this->records->listing($query);
    }

    public function get(Account $actor, string $id): RecommendedRoute
    {
        $this->authorization->requireAny($actor, 'routes', ['view','edit']);
        return $this->target($id);
    }

    /** @return array<string,mixed> */
    public function candidates(Account $actor, string $q, int $page): array
    {
        $this->authorization->requireAny($actor, 'routes', ['view','create','edit']);
        $items = $this->records->candidates($q, $page);
        return ['items' => array_slice($items, 0, 20),'hasNext' => count($items) > 20,'page' => $page];
    }

    /** @param array<string,mixed> $values
     */
    public function save(Account $actor, ?string $id, array $values, ClientContext $client): RouteWriteResult
    {
        $this->authorization->require($actor, 'routes', $id === null ? 'create' : 'edit');
        $input = RecommendedRouteInput::from($values, $id === null);
        return $this->publication->exclusive(function () use ($actor, $id, $input, $client): RouteWriteResult {
            $record = $this->transactions->run(function () use ($actor, $id, $input, $client): RecommendedRoute {
                $fresh = $this->authorization->require($actor, 'routes', $id === null ? 'create' : 'edit', true);
                $before = $id === null ? null : $this->target($id, true);
                if ($before !== null && $before->version !== $input->version) {
                    throw new ApplicationError('CONFLICT', '資料已被其他人修改，請重新載入。');
                }
                $target = $id ?? Secrets::uuid();
                $this->records->assertAvailable($input);
                $this->records->save($target, $input, $id === null);
                $oldVersion = $before === null ? 0 : $before->version;
                $this->events->content(
                    $fresh->id,
                    $id === null ? 'route-create' : 'route-update',
                    'routes',
                    $target,
                    $oldVersion,
                    $oldVersion + 1,
                    $input->status->value,
                    $client,
                );
                return $this->target($target);
            });
            return new RouteWriteResult($record, $this->publication->synchronize());
        });
    }

    public function delete(Account $actor, string $id, int $version, ClientContext $client): RouteWriteResult
    {
        return $this->publication->exclusive(function () use ($actor, $id, $version, $client): RouteWriteResult {
            $this->transactions->run(function () use ($actor, $id, $version, $client): void {
                $fresh = $this->authorization->require($actor, 'routes', 'delete', true);
                $before = $this->target($id, true);
                if ($before->version !== $version) {
                    throw new ApplicationError('CONFLICT', '資料已變更，請重新載入。');
                }
                $this->records->delete($id);
                $this->events->content($fresh->id, 'route-delete', 'routes', $id, $version, $version + 1, 'archived', $client);
            });
            return new RouteWriteResult(null, $this->publication->synchronize());
        });
    }

    private function target(string $id, bool $lock = false): RecommendedRoute
    {
        ContentFields::uuid($id, 'id');
        $record = $this->records->find($id, $lock);
        if ($record === null || $record->deletedAt !== null) {
            throw new ApplicationError('NOT_FOUND', '找不到此推薦路線。');
        }
        return $record;
    }

}
