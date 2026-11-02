<?php

declare(strict_types=1);

namespace Yinghua\Exceptions;

use Yinghua\Services\PublicationSnapshotBuilder;

/** 公開頁面尚未同步；診斷訊息只記固定的區段名稱與 hash，不寫入公開內容、路徑或設定值。 */
class PublicationPending extends ApplicationError
{
    private readonly string $diagnostic;

    /** @param array<string,mixed>|null $current
     * @param array<string,mixed> $expected
     */
    public function __construct(?array $current, array $expected)
    {
        $sections = [];
        foreach (PublicationSnapshotBuilder::SECTIONS as $key) {
            if ($current !== null && ($current[$key] ?? null) !== ($expected[$key] ?? null)) {
                $sections[] = $key;
            }
        }
        $hash = static fn(mixed $value): string => is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) ? $value : 'unavailable';
        $this->diagnostic = 'publication_reason=' . ($current === null ? 'missing_snapshot' : 'projection_changed')
            . ' published=' . $hash($current['generation'] ?? null) . ' expected=' . $hash($expected['generation'] ?? null)
            . ' sections=' . ($sections === [] ? 'none' : implode(',', $sections));
        parent::__construct(ErrorCode::PublicationPending, '公開頁面正在更新，請稍後重試。', retryAfter: 5);
    }

    public function diagnostic(): string
    {
        return $this->diagnostic;
    }
}
