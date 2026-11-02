<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportEnvelope;
use Yinghua\DTO\ImportItem;
use Yinghua\Models\Account;
use Yinghua\Repositories\MerchantRepository;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\MerchantManagement;
use Yinghua\Services\NewsManagement;
use Yinghua\Services\OfferManagement;

/** 匯入原型的示範內容；只建立缺少的資料，不更新已存在的正式或示範資料。 */
class PublicContentSeeder
{
    public function __construct(
        private readonly MerchantRepository $merchantRepository,
        private readonly NewsRepository $newsRepository,
        private readonly OfferRepository $offerRepository,
        private readonly MerchantManagement $merchants,
        private readonly NewsManagement $news,
        private readonly OfferManagement $offers,
        private readonly Transactions $transactions,
        private readonly ContentPublication $publication,
    ) {}

    /** @return array<string,mixed> */
    public function seed(Account $actor, ClientContext $client): array
    {
        $source = dirname(__DIR__, 2) . '/resources/';
        $merchantRows = $this->source($source . 'public-merchants-demo.json');
        $newsRows = $this->source($source . 'public-news-demo.json');
        $offerRows = $this->source($source . 'public-offers-demo.json');
        return $this->publication->exclusive(function () use ($actor, $client, $merchantRows, $newsRows, $offerRows): array {
            $results = $this->transactions->run(function () use ($actor, $client, $merchantRows, $newsRows, $offerRows): array {
                $results = [];
                $merchantIds = [];
                foreach ($merchantRows as $item) {
                    $key = $item->values['attractionLegacyId'] ?? null;
                    if (!is_int($key) || !in_array($key, [3,5,6], true)) {
                        throw new \RuntimeException('Unexpected merchant demonstration source.');
                    }
                    $existing = $this->merchantRepository->forAttractionLegacyId($key) ?? $this->merchantRepository->find($item->id, true);
                    if ($existing !== null) {
                        $merchantIds[$key] = $existing->id;
                        $results[] = ['id' => $existing->id,'action' => 'preserved','module' => 'merchants'];
                        continue;
                    }
                    $fields = $item->values;
                    unset($fields['attractionLegacyId']);
                    $change = $this->merchants->prepare($actor, new ImportItem($item->id, $fields), true);
                    $this->merchants->save($actor, $change, $client);
                    $merchantIds[$key] = $item->id;
                    $results[] = [...$change->summary(),'module' => 'merchants'];
                }
                foreach ($newsRows as $item) {
                    if ($this->newsRepository->find($item->id, true) !== null) {
                        $results[] = ['id' => $item->id,'action' => 'preserved','module' => 'news'];
                        continue;
                    }
                    $legacy = $item->values['legacyId'] ?? null;
                    if (!is_int($legacy) || $legacy < 0 || $legacy > 3) {
                        throw new \RuntimeException('Unexpected news demonstration source.');
                    }
                    $fields = $item->values;
                    unset($fields['legacyId']);
                    $change = $this->news->prepare($actor, new ImportItem($item->id, $fields), true);
                    $this->news->save($actor, $change, $client);
                    $this->newsRepository->demoIdentity($item->id, $legacy);
                    $results[] = [...$change->summary(),'module' => 'news'];
                }
                foreach ($offerRows as $item) {
                    if ($this->offerRepository->find($item->id, true) !== null) {
                        $results[] = ['id' => $item->id,'action' => 'preserved','module' => 'offers'];
                        continue;
                    }
                    $key = $item->values['attractionLegacyId'] ?? null;
                    $legacy = $item->values['legacyId'] ?? null;
                    if (!is_int($key) || !isset($merchantIds[$key]) || !is_int($legacy) || $legacy < 101 || $legacy > 103) {
                        throw new \RuntimeException('Unexpected offer demonstration source.');
                    }
                    $fields = [...$item->values,'merchantId' => $merchantIds[$key]];
                    unset($fields['legacyId'], $fields['attractionLegacyId']);
                    $change = $this->offers->prepare($actor, new ImportItem($item->id, $fields), true);
                    $this->offers->save($actor, $change, $client);
                    $this->offerRepository->demoIdentity($item->id, $legacy);
                    if ($legacy === 103) {
                        $this->offerRepository->alias('/news/coffee-walk.html', $item->id);
                    }
                    $results[] = [...$change->summary(),'module' => 'offers'];
                }
                return $results;
            });
            $ready = $this->publication->synchronize();
            return ['status' => $ready ? 'saved' : 'saved_publication_pending','requestId' => $client->requestId,'items' => $results,
                'warning' => '原型示範資料及圖片須於正式上線前確認或替換；既有資料不覆寫。'];
        });
    }

    /** @return list<ImportItem> */
    private function source(string $path): array
    {
        $text = file_get_contents($path);
        if ($text === false) {
            throw new \RuntimeException('Demonstration source unavailable.');
        }
        return ImportEnvelope::fromJson($text)->items;
    }
}
