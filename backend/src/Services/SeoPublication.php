<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\SeoOverride;
use Yinghua\Models\SeoResource;
use Yinghua\Repositories\ContentSeoRepository;
use Yinghua\Repositories\SiteSettingsRepository;

/** [S：G30/G32；E-28] 快照只含頁面與目前可公開內容的 SEO，草稿覆寫不出界。 */
class SeoPublication
{
    public function __construct(private readonly SiteSettingsRepository $settings, private readonly ContentSeoRepository $overrides) {}

    /** @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    public function project(array $snapshot): array
    {
        $document = $this->settings->read(publishedOnly: true);
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
