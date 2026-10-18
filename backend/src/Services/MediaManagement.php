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
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\MediaRepository;

class MediaManagement
{
    public function __construct(private readonly MediaRepository $media, private readonly ImageStorage $storage, private readonly Authorization $authorization, private readonly Transactions $transactions, private readonly EventRepository $events) {}

    public function upload(Account $actor, UploadedImage $file, string $action, string $alt, string $source, string $license, ClientContext $client): MediaAsset
    {
        $this->authorization->require($actor, 'attractions', $action);
        $asset = $this->storage->store($file, $alt, $source, $license);
        try {
            $this->transactions->run(function () use ($actor, $asset, $action, $client): void {
                $fresh = $this->authorization->require($actor, 'attractions', $action, true);
                $this->media->save($asset, $fresh->id);
                $this->events->content($fresh->id, 'media-upload', 'media', $asset->id, null, 1, 'stored', $client);
            });
        } catch (Throwable $error) {
            $this->storage->discard($asset);
            throw $error;
        }

        return $asset;
    }

    /** @return array<string,mixed> */
    public function listing(Account $actor, int $page, int $size): array
    {
        $this->authorization->require($actor, 'attractions', 'view');

        return ['items' => $this->media->listing($page, $size),'page' => $page,'pageSize' => $size];
    }
}
