<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\MediaAsset;

class MediaRepository
{
    public function __construct(private readonly Database $database) {}

    public function save(MediaAsset $asset, string $actorId): void
    {
        $this->database->execute(
            'INSERT INTO media_assets (id,storage_key,mime_type,width,height,byte_size,alt,source,license,module,uploaded_by) VALUES (:id,:key,:mime,:width,:height,:size,:alt,:source,:license,\'attractions\',:actor)',
            ['id' => $asset->id,'key' => $asset->storageKey,'mime' => $asset->mimeType,'width' => $asset->width,'height' => $asset->height,
                'size' => $asset->byteSize,'alt' => $asset->alt,'source' => $asset->source,'license' => $asset->license,'actor' => $actorId],
        );
    }

    /** @return list<array<string,mixed>> */
    public function listing(int $page, int $size): array
    {
        return array_map(function (array $values): array {
            $r = new Row($values);

            return (new MediaAsset(
                $r->string('id'),
                $r->string('storage_key'),
                $r->string('mime_type'),
                $r->integer('width'),
                $r->integer('height'),
                $r->integer('byte_size'),
                $r->string('alt'),
                $r->string('source'),
                $r->string('license'),
            ))->toArray();
        }, $this->database->rows("SELECT id,storage_key,mime_type,width,height,byte_size,alt,source,license FROM media_assets WHERE module='attractions' ORDER BY created_at DESC,id DESC LIMIT :size OFFSET :offset", ['size' => $size,'offset' => ($page - 1) * $size]));
    }
}
