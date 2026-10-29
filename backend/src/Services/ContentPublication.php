<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Throwable;
use Yinghua\Config;
use Yinghua\Contracts\ContentRenderer;
use Yinghua\Exceptions\PublicationPending;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\PublicationRepository;

/** [E-12／E-18] DB 是編輯來源；成功產生的不可變快照是公開讀取版本。 */
class ContentPublication
{
    public function __construct(
        private readonly AttractionRepository $attractions,
        private readonly PublicationRepository $publications,
        private readonly ContentRenderer $renderer,
        private readonly Config $config,
        private readonly ?\Yinghua\Repositories\FilmRepository $films = null,
        private readonly ?\Yinghua\Contracts\Clock $clock = null,
        private readonly ?\Yinghua\Repositories\NewsRepository $news = null,
        private readonly ?\Yinghua\Repositories\OfferRepository $offers = null,
        private readonly ?SeoPublication $seo = null,
    ) {}

    /** @template T
     * @param \Closure():T $callback
     * @return T
     */
    public function exclusive(\Closure $callback): mixed
    {
        return $this->renderer->exclusive($callback);
    }

    /** [E-18] 呼叫端已提交內容；發布任何步驟失敗都須保留已保存的回應，不讓使用者重複新增。 */
    public function synchronize(): bool
    {
        $id = null;
        try {
            $snapshot = $this->projection();
            $id = $this->publications->begin($this->generation($snapshot));
            $this->renderer->render($snapshot);
            $this->publications->finish($id, true);

            return true;
        } catch (Throwable $error) {
            // 紀錄資料庫本身可能故障；記錄失敗不能遮蔽「內容已保存」。
            if ($id !== null) {
                try {
                    $this->publications->finish($id, false);
                } catch (Throwable $recordError) {
                    error_log('publication=' . $id . ' record_exception=' . $recordError::class);
                }
            }
            error_log('publication=' . ($id ?? 'unrecorded') . ' exception=' . $error::class);

            return false;
        }
    }

    public function retry(): bool
    {
        return $this->exclusive($this->synchronize(...));
    }

    /** 排程由 CLI 週期執行；沒有變更就不重產生。 */
    public function tick(): bool
    {
        return $this->exclusive(function (): bool {
            return $this->ready() || $this->synchronize();
        });
    }

    public function ready(): bool
    {
        return ($this->renderer->current()['generation'] ?? null) === $this->generation($this->projection());
    }

    /** @return array<string,mixed> */
    public function read(): array
    {
        // [S-SEO-17] 產生失敗／下架／排程切換期間不繼續公開舊版本。
        $current = $this->renderer->current();
        if ($current === null) {
            throw new PublicationPending(null, []);
        }
        $expected = $this->projection();
        if (($current['generation'] ?? null) !== $this->generation($expected)) {
            throw new PublicationPending($current, $expected);
        }

        return $current;
    }

    /** @return array<string,mixed> */
    private function projection(): array
    {
        $now = $this->clock?->now() ?? time();
        $window = \Yinghua\DTO\ScreeningWindow::current($now);
        $data = ['origin' => $this->config->origin(),'rendererVersion' => $this->renderer->version(),'policyVersion' => $this->config->string('TERMS_VERSION', 'local-development-v1'),
            'items' => array_map(static fn(\Yinghua\DTO\Attraction $item): array => $item->publicArray(), $this->attractions->published($now)),
            'aliases' => $this->attractions->aliases($now)];
        // [E-25] 景點與電影共享 generation；本週邊界使無編輯的換週也會發布。
        if ($this->films !== null) {
            $data['films'] = array_map(static fn(\Yinghua\DTO\Film $film): array => $film->publicArray(), $this->films->published($now));
            $data['filmAliases'] = $this->films->aliases($now);
            $data['filmPeriod'] = $window->toArray();
        }
        if ($this->news !== null) {
            $data['news'] = array_map(static fn(\Yinghua\DTO\NewsPost $post): array => $post->publicArray(), $this->news->published($now));
            $data['newsAliases'] = $this->news->aliases($now);
        }
        if ($this->offers !== null) {
            $today = \Yinghua\Policies\OfferValidity::day($now);
            $data['offerDate'] = $today;
            $data['offers'] = array_map(static function (\Yinghua\DTO\Offer $offer) use ($today): array {
                $item = $offer->publicArray();
                $from = is_string($item['validFrom'] ?? null) ? $item['validFrom'] : null;
                $to = is_string($item['validTo'] ?? null) ? $item['validTo'] : null;
                $state = \Yinghua\Policies\OfferValidity::state($from, $to, $today);
                return [...$item,'validity' => $state,'active' => $state === 'active'];
            }, $this->offers->published($now));
            $data['offerAliases'] = $this->offers->aliases($now);
        }
        $data['detailNeighbors'] = \Yinghua\Policies\NeighborSelection::project($data);
        $data['home'] = \Yinghua\Policies\HomeSelection::fromSnapshot($data, $window)->toArray();
        if ($this->seo !== null) {
            $data['seo'] = $this->seo->project($data);
        }
        $data['generation'] = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $data;
    }

    /** @param array<string,mixed> $snapshot */
    private function generation(array $snapshot): string
    {
        $generation = $snapshot['generation'] ?? null;
        if (!is_string($generation)) {
            throw new \RuntimeException('Invalid publication generation.');
        }

        return $generation;
    }
}
