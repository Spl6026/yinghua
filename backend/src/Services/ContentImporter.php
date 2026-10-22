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

/** [E-17／26] 先整批驗證、同交易寫入，再同步公開版；已提交不假裝回滾。 */
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
                // 發布紀錄本身故障同樣只回待同步，不撤回已保存內容。
                $ready = $this->publication->synchronize();
                return [$changes, $ready];
            });
            $changed = array_any($changes, static fn(PreparedImport $change): bool => !$change->unchanged);
            $status = !$ready ? 'saved_publication_pending' : ($changed ? 'saved' : 'unchanged');
        }
        return ['status' => $status,'requestId' => $client->requestId,
            'items' => array_map(static fn(PreparedImport $change): array => $change->summary(), $changes),
            'manifest' => ['schemaVersion' => 1,'items' => array_map(static fn(PreparedImport $change): array => $change->manifestItem(), $changes)]];
    }
}
