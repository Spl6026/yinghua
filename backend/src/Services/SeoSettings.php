<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Adapters\SeoCatalog;
use Yinghua\Config;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\SeoOverride;
use Yinghua\DTO\SeoSettingsDocument;
use Yinghua\DTO\SeoSettingsInput;
use Yinghua\DTO\SeoSettingsResult;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\SeoResource;
use Yinghua\Repositories\SeoOverrideRepository;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\SeoTargetRepository;
use Yinghua\Repositories\SeoSettingsRepository;

/** SEO 設定管理：預設值、個別覆寫與稽核紀錄在同一交易寫入，提交後才重新產生 HTML。 */
class SeoSettings
{
    public function __construct(
        private readonly SeoSettingsRepository $settings,
        private readonly SeoOverrideRepository $overrides,
        private readonly SeoTargetRepository $content,
        private readonly Authorization $authorization,
        private readonly Transactions $transactions,
        private readonly EventRepository $events,
        private readonly ContentPublication $publication,
        private readonly SeoCatalog $catalog,
        private readonly Config $config,
    ) {}

    public function read(Account $actor, string $query = ''): SeoSettingsResult
    {
        $actor = $this->authorization->require($actor, 'settings', 'view');
        $document = $this->document();
        $visibility = $this->visibility($actor, $document->overrides);
        $visible = array_values(array_filter($document->overrides, $visibility));
        $modules = $this->viewModules($actor);
        $targets = [];
        $truncated = false;
        foreach (SeoResource::cases() as $type) {
            if ($type === SeoResource::Page) {
                continue;
            }
            $rows = $this->content->choices($type, in_array($type->module(), $modules, true), $query);
            $truncated = $truncated || count($rows) > 50;
            array_push($targets, ...array_slice($rows, 0, 50));
        }
        return new SeoSettingsResult(
            new SeoSettingsDocument($document->version, $document->defaults, $visible, $document->updatedAt),
            $this->published($document->version),
            $this->catalog->pages,
            $targets,
            $truncated,
        );
    }

    public function save(Account $actor, SeoSettingsInput $input, ClientContext $client): SeoSettingsResult
    {
        return $this->publication->exclusive(function () use ($actor, $input, $client): SeoSettingsResult {
            $document = $this->transactions->run(function () use ($actor, $input, $client): SeoSettingsDocument {
                $fresh = $this->authorization->require($actor, 'settings', 'edit', true);
                $before = $this->document(true);
                if ($before->version !== $input->version) {
                    throw new ApplicationError('CONFLICT', 'SEO 設定已由其他人更新，請重新讀取後再編輯。');
                }
                $visibility = $this->visibility($fresh, [...$before->overrides, ...$input->overrides]);
                foreach ($input->overrides as $index => $item) {
                    if (!$visibility($item)) {
                        throw ApplicationError::invalid('overrides.' . $index . '.target', '找不到可用或有權讀取的內容。');
                    }
                }
                // 使用者無權看到的覆寫要原樣保留，不能因為送出整份設定就被一併刪掉。
                $existingTargets = $this->content->lookup($before->overrides);
                $hidden = array_values(array_filter($before->overrides, static fn(SeoOverride $item): bool => !$visibility($item)
                    && isset($existingTargets[$item->key()])));
                $all = [...$input->overrides, ...$hidden];
                if (count($all) > 100) {
                    throw ApplicationError::invalid('overrides', '目前最多保存 100 筆 SEO 覆寫。');
                }
                $images = ['defaults.ogImageUrl' => $input->defaults->ogImageUrl];
                foreach ($input->overrides as $index => $item) {
                    $images['overrides.' . $index . '.ogImageUrl'] = $item->ogImageUrl ?? '';
                }
                $this->assertImages($images, $input->defaults->canonicalOrigin);
                $version = $before->version + 1;
                $this->settings->save($input->defaults, $version, $fresh->id, $before->version === 0);
                $this->overrides->replace($all, $fresh->id);
                $this->events->content($fresh->id, 'seo-update', 'site_settings', 'seo', $before->version, $version, 'published', $client);
                return $this->document();
            });
            $ready = $this->publication->synchronize() && $this->published($document->version);
            $visible = array_values(array_filter($document->overrides, $this->visibility($actor, $document->overrides)));
            return new SeoSettingsResult(
                new SeoSettingsDocument($document->version, $document->defaults, $visible, $document->updatedAt),
                $ready,
            );
        });
    }

    public function document(bool $lock = false): SeoSettingsDocument
    {
        $setting = $this->settings->read($lock);
        return new SeoSettingsDocument($setting->version, $setting->defaults, $this->overrides->all(), $setting->updatedAt);
    }

    private function published(int $version): bool
    {
        try {
            $seo = $this->publication->read()['seo'] ?? null;
            return is_array($seo) ? ($seo['version'] ?? null) === $version : $version === 0;
        } catch (ApplicationError) {
            return false;
        }
    }

    /** @return list<string> */
    private function viewModules(Account $actor): array
    {
        $modules = [];
        foreach ($this->authorization->permissions($actor) as $right) {
            if ($right['action'] === 'view' && $right['allowed']) {
                $modules[] = $right['module'];
            }
        }
        return $modules;
    }

    /** @param list<SeoOverride> $overrides
     * @return \Closure(SeoOverride):bool
     */
    private function visibility(Account $actor, array $overrides): \Closure
    {
        $targets = $this->content->lookup($overrides);
        $modules = $this->viewModules($actor);
        return static function (SeoOverride $item) use ($targets, $modules): bool {
            if ($item->type === SeoResource::Page) {
                return true;
            }
            $target = $targets[$item->key()] ?? null;
            return $target !== null && ($target->published || in_array($item->type->module(), $modules, true));
        };
    }

    /** @param array<string,string> $images */
    private function assertImages(array $images, string $canonicalOrigin): void
    {
        $hosts = array_filter([parse_url($this->config->origin(), PHP_URL_HOST), parse_url($canonicalOrigin, PHP_URL_HOST)]);
        foreach ($images as $field => $image) {
            $path = parse_url($image, PHP_URL_PATH);
            if (!is_string($path) || !str_starts_with($path, '/media/') || !in_array(parse_url($image, PHP_URL_HOST), $hosts, true)) {
                continue;
            }
            $snapshot = $this->publication->read();
            $found = false;
            foreach (['items','films','news','offers'] as $key) {
                $items = $snapshot[$key] ?? [];
                foreach (is_array($items) ? $items : [] as $item) {
                    if (is_array($item)
                        && (($item['image'] ?? null) === $path
                            || (is_array($item['bodyImages'] ?? null) && in_array($path, $item['bodyImages'], true)))) {
                        $found = true;
                    }
                }
            }
            if (!$found) {
                throw ApplicationError::invalid($field, '本站分享圖片須已公開可讀，不能引用草稿媒體。');
            }
        }
    }
}
