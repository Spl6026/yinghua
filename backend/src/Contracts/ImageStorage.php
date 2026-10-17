<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

use Yinghua\DTO\MediaAsset;
use Yinghua\DTO\UploadedImage;

interface ImageStorage
{
    public function store(UploadedImage $upload, string $alt, string $source, string $license): MediaAsset;

    public function discard(MediaAsset $asset): void;
}
