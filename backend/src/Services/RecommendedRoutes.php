<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\{AdminContentQuery, ClientContext, RecommendedRoute, RecommendedRouteInput, RouteWriteResult};
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\{Account, ContentStatus};
use Yinghua\Repositories\{RecommendedRouteRepository};
use Yinghua\Exceptions\ErrorCode;
use Yinghua\Support\Uuid;

/** 推薦路線的後台管理：權限、交易、版本比對與發布都集中在這裡，Controller 不直接操作資料庫。 */
class RecommendedRoutes
{
    public function __construct(
        private readonly RecommendedRouteRepository $records,
        private readonly Authorization $authorization,
        private readonly ContentWriter $writer,
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
        [$record, $synchronized] = $this->writer->save(
            $actor,
            'routes',
            ['route', 'routes'],
            $id,
            $input->version,
            $this->target(...),
            function (?RecommendedRoute $before, string $target) use ($input): RecommendedRoute {
                $this->records->assertAvailable($input);
                $this->records->save($target, $input, $before === null);

                return $this->target($target);
            },
            $client,
        );
        return new RouteWriteResult($record, $synchronized);
    }

    public function delete(Account $actor, string $id, int $version, ClientContext $client): RouteWriteResult
    {
        $synchronized = $this->writer->delete(
            $actor,
            'routes',
            ['route', 'routes'],
            $id,
            $version,
            $this->target(...),
            fn(RecommendedRoute $route) => $this->records->delete($route->id),
            $client,
        );
        return new RouteWriteResult(null, $synchronized);
    }

    private function target(string $id, bool $lock = false): RecommendedRoute
    {
        $record = Uuid::isValid($id) ? $this->records->find($id, $lock) : null;
        if ($record === null || $record->deletedAt !== null) {
            throw new ApplicationError(ErrorCode::NotFound, '找不到此推薦路線。');
        }
        return $record;
    }

}
