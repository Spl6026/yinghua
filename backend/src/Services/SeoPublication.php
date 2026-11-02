<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\SeoOverride;
use Yinghua\Models\SeoResource;
use Yinghua\Repositories\SeoOverrideRepository;
use Yinghua\Repositories\SeoSettingsRepository;

/** 產生發布快照用的 SEO 資料；只包含頁面與目前公開內容的設定，草稿內容的覆寫不會外流。 */
class SeoPublication
{
    public function __construct(private readonly SeoSettingsRepository $settings, private readonly SeoOverrideRepository $overrides) {}

    /** @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    public function project(array $snapshot): array
    {
        $document = $this->settings->read();
        $ids = [];
        foreach (['items' => 'attraction','films' => 'film','news' => 'news','offers' => 'offer'] as $key => $type) {
            $items = $snapshot[$key] ?? [];
            foreach (is_array($items) ? $items : [] as $item) {
                if (is_array($item) && is_string($item['id'] ?? null)) {
                    $ids[$type . ':' . $item['id']] = true;
                }
            }
        }
        $public = $document->version === 0 ? [] : array_values(array_filter(
            $this->overrides->all(),
            static fn(SeoOverride $item): bool => $item->type === SeoResource::Page || isset($ids[$item->key()]),
        ));

        return ['version' => $document->version,'updatedAt' => $document->updatedAt,'defaults' => $document->defaults->toArray(),
            'overrides' => array_map(static fn(SeoOverride $item): array => $item->toArray(), $public)];
    }
}
