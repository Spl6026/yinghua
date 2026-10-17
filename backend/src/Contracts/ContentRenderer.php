<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

interface ContentRenderer
{
    public function version(): string;

    /** @param array<string,mixed> $snapshot */
    public function render(array $snapshot): void;

    /** @return array<string,mixed>|null */
    public function current(): ?array;

    /** @template T
     * @param \Closure():T $callback
     * @return T
     */
    public function exclusive(\Closure $callback): mixed;
}
