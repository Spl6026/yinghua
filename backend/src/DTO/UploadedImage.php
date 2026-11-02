<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\ImageOrigin;

readonly class UploadedImage
{
    public function __construct(
        public string $temporaryPath,
        public int $error,
        public int $size,
        public ImageOrigin $origin = ImageOrigin::HttpUpload,
    ) {}
}
