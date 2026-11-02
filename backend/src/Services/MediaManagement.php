<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Throwable;
use Yinghua\Contracts\ImageStorage;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\MediaAsset;
use Yinghua\DTO\UploadedImage;
use Yinghua\Models\Account;
use Yinghua\Models\MediaModule;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\MediaRepository;

class MediaManagement
{
    public function __construct(
        private readonly MediaRepository $media,
        private readonly ImageStorage $storage,
        private readonly Authorization $authorization,
        private readonly Transactions $transactions,
        private readonly EventRepository $events,
    ) {}

    public function upload(
        Account $actor,
        UploadedImage $file,
        string $action,
        string $alt,
        string $source,
        string $license,
        ClientContext $client,
        MediaModule $module = MediaModule::Attractions,
    ): MediaAsset {
        $this->authorization->require($actor, $module->value, $action);
        // 後台上傳與 CLI 匯入共用同一套 alt／來源／授權欄位驗證。
        $input = new \Yinghua\DTO\Input(['alt' => $alt,'source' => $source,'license' => $license]);
        $alt = $input->text('alt');
        $source = $input->optionalText('source');
        $license = $input->optionalText('license');
        $asset = $this->storage->store($file, $alt, $source, $license);
        try {
            $this->transactions->run(function () use ($actor, $asset, $action, $client, $module): void {
                $fresh = $this->authorization->require($actor, $module->value, $action, true);
                $this->media->save($asset, $fresh->id, $module);
                $this->events->content($fresh->id, 'media-upload', 'media', $asset->id, null, 1, 'stored', $client);
            });
        } catch (Throwable $error) {
            $this->storage->discard($asset);
            throw $error;
        }

        return $asset;
    }

    /** @return array<string,mixed> */
    public function listing(Account $actor, int $page, int $size, MediaModule $module = MediaModule::Attractions): array
    {
        $this->authorization->require($actor, $module->value, 'view');

        return ['items' => $this->media->listing($page, $size, $module),'page' => $page,'pageSize' => $size];
    }
}
