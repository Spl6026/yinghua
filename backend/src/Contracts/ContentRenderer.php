<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

/** 產生公開頁面、讀取已發布快照，並以檔案鎖確保同一時間只有一個發布流程。 */
interface ContentRenderer extends PublishedSnapshot
{
    public function version(): string;

    /** @param array<string,mixed> $snapshot */
    public function render(array $snapshot): void;

    /** @template T
     * @param \Closure():T $callback
     * @return T
     */
    public function exclusive(\Closure $callback): mixed;
}
