<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\MediaAsset;
use Yinghua\Models\MediaModule;

class MediaRepository
{
    public function __construct(private readonly Database $database) {}

    public function save(MediaAsset $asset, string $actorId, MediaModule $module = MediaModule::Attractions): void
    {
        $this->database->execute(
            'INSERT INTO media_assets (id,storage_key,mime_type,width,height,byte_size,alt,source,license,module,uploaded_by) VALUES (:id,:key,:mime,:width,:height,:size,:alt,:source,:license,:module,:actor)',
            [
                'id' => $asset->id,
                'key' => $asset->storageKey,
                'mime' => $asset->mimeType,
                'width' => $asset->width,
                'height' => $asset->height,
                'size' => $asset->byteSize,
                'alt' => $asset->alt,
                'source' => $asset->source,
                'license' => $asset->license,
                'actor' => $actorId,
                'module' => $module->value,
            ],
        );
    }

    /** @return list<array<string,mixed>> */
    public function listing(int $page, int $size, MediaModule $module = MediaModule::Attractions): array
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
        }, $this->database->rows(
            'SELECT id,storage_key,mime_type,width,height,byte_size,alt,source,license FROM media_assets WHERE module=:module ORDER BY created_at DESC,id DESC LIMIT :size OFFSET :offset',
            ['module' => $module->value,'size' => $size,'offset' => ($page - 1) * $size],
        ));
    }

    public function find(string $id, MediaModule $module): ?MediaAsset
    {
        $values = $this->database->row(
            'SELECT * FROM media_assets WHERE id=:id AND module=:module',
            ['id' => $id,'module' => $module->value],
        );
        if ($values === null) {
            return null;
        }
        $row = new Row($values);
        return new MediaAsset(
            $row->string('id'),
            $row->string('storage_key'),
            $row->string('mime_type'),
            $row->integer('width'),
            $row->integer('height'),
            $row->integer('byte_size'),
            $row->string('alt'),
            $row->string('source'),
            $row->string('license'),
        );
    }

    public function moduleForStorage(string $key): ?MediaModule
    {
        $row = $this->database->row('SELECT module FROM media_assets WHERE storage_key=:key', ['key' => $key]);
        return $row === null ? null : MediaModule::tryFrom((new Row($row))->string('module'));
    }
}
