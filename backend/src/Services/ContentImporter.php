<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\ContentImportService;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportEnvelope;
use Yinghua\DTO\PreparedImport;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;

/**
 * 匯入流程：先整批驗證，再於同一交易內寫入，最後同步公開版本。
 * 資料一旦提交就不會因發布失敗而回滾，改以「待同步」狀態回報。
 */
class ContentImporter
{
    public function __construct(private readonly Transactions $transactions, private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function run(Account $actor, ImportEnvelope $input, ContentImportService $service, bool $apply, ClientContext $client): array
    {
        $prepare = static function () use ($actor, $input, $service, $apply): array {
            $changes = [];
            foreach ($input->items as $index => $item) {
                try {
                    $changes[] = $service->prepare($actor, $item, $apply);
                } catch (ApplicationError $error) {
                    $errors = [];
                    foreach ($error->fieldErrors as $key => $message) {
                        $errors['items.' . $index . '.' . $key] = $message;
                    }
                    throw new ApplicationError($error->errorCode, $error->getMessage(), $errors);
                }
            }
            return $changes;
        };
        if (!$apply) {
            $changes = $prepare();
            $status = 'dry_run';
        } else {
            [$changes, $ready] = $this->publication->exclusive(function () use ($prepare, $service, $actor, $client): array {
                $changes = $this->transactions->run(function () use ($prepare, $service, $actor, $client): array {
                    $changes = $prepare();
                    foreach ($changes as $change) {
                        if (!$change->unchanged) {
                            $service->save($actor, $change, $client);
                        }
                    }
                    return $changes;
                });
                // 即使發布紀錄本身出錯，也只回報待同步，不撤回已保存的內容。
                $ready = $this->publication->synchronize();
                return [$changes, $ready];
            });
            $changed = array_any($changes, static fn(PreparedImport $change): bool => !$change->unchanged);
            $status = !$ready ? 'saved_publication_pending' : ($changed ? 'saved' : 'unchanged');
        }
        return ['status' => $status,'requestId' => $client->requestId,
            'items' => array_map(static fn(PreparedImport $change): array => $change->summary(), $changes),
            'manifest' => ['schemaVersion' => 1,
                'items' => array_map(static fn(PreparedImport $change): array => $change->manifestItem(), $changes)]];
    }
}
