<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\GeneralSettingsInput;
use Yinghua\DTO\SeoDefaults;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\GeneralSettingsRepository;
use Yinghua\Repositories\SiteSettingsRepository;

class GeneralSiteSettings
{
    public function __construct(private readonly GeneralSettingsRepository $settings, private readonly SiteSettingsRepository $seo, private readonly Authorization $authorization, private readonly Transactions $transactions, private readonly EventRepository $events, private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function read(Account $actor): array
    {
        $this->authorization->require($actor, 'settings', 'view');
        return $this->document();
    }

    /** @return array<string,mixed> */
    private function document(): array
    {
        $seo = $this->seo->read();
        return [...$this->settings->read()->toArray(), 'seoVersion' => $seo->version, 'siteName' => $seo->defaults->siteName, 'description' => $seo->defaults->description];
    }

    /** @return array<string,mixed> */
    public function save(Account $actor, GeneralSettingsInput $input, ClientContext $client): array
    {
        return $this->publication->exclusive(function () use ($actor, $input, $client): array {
            $this->transactions->run(function () use ($actor, $input, $client): void {
                $fresh = $this->authorization->require($actor, 'settings', 'edit', true);
                $before = $this->settings->read(true);
                $seo = $this->seo->read(true);
                if ($before->version !== $input->version || $seo->version !== $input->seoVersion) {
                    throw new ApplicationError('CONFLICT', '設定已更新，請重新讀取後再編輯。');
                }
                $defaults = SeoDefaults::fromArray([...$seo->defaults->toArray(), 'siteName' => $input->siteName, 'description' => $input->description]);
                $this->settings->save($input->values, $before->version + 1, $fresh->id, $before->version === 0);
                $this->seo->save($defaults, $seo->version + 1, $fresh->id, $seo->version === 0);
                $this->events->content($fresh->id, 'general-update', 'site_settings', 'general', $before->version, $before->version + 1, 'published', $client);
            });
            $ready = $this->publication->synchronize();
            return [...$this->document(), 'publicationReady' => $ready];
        });
    }
}
