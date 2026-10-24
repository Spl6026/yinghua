<?php

declare(strict_types=1);

namespace Yinghua\Exceptions;

/** [S：B7/B13；E-29] 只保存固定分類與 hash，不將公開內容、路徑或設定值寫入診斷。 */
class PublicationPending extends ApplicationError
{
    private readonly string $diagnostic;

    /** @param array<string,mixed>|null $current
     * @param array<string,mixed> $expected
     */
    public function __construct(?array $current, array $expected)
    {
        $sections = [];
        foreach (['origin','rendererVersion','policyVersion','items','aliases','films','filmAliases','filmPeriod','news','newsAliases','offerDate','offers','offerAliases','home','seo'] as $key) {
            if ($current !== null && ($current[$key] ?? null) !== ($expected[$key] ?? null)) {
                $sections[] = $key;
            }
        }
        $hash = static fn(mixed $value): string => is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) ? $value : 'unavailable';
        $this->diagnostic = 'publication_reason=' . ($current === null ? 'missing_snapshot' : 'projection_changed')
            . ' published=' . $hash($current['generation'] ?? null) . ' expected=' . $hash($expected['generation'] ?? null)
            . ' sections=' . ($sections === [] ? 'none' : implode(',', $sections));
        parent::__construct('PUBLICATION_PENDING', '公開頁面正在更新，請稍後重試。', retryAfter: 5);
    }

    public function diagnostic(): string
    {
        return $this->diagnostic;
    }
}
