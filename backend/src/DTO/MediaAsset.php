<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class MediaAsset
{
    public function __construct(public string $id, public string $storageKey, public string $mimeType, public int $width, public int $height, public int $byteSize, public string $alt, public string $source, public string $license) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id,'url' => '/media/' . $this->storageKey,'mimeType' => $this->mimeType,'width' => $this->width,'height' => $this->height,'byteSize' => $this->byteSize,'alt' => $this->alt,'source' => $this->source,'license' => $this->license];
    }
}
