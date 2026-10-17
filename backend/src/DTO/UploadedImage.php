<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class UploadedImage
{
    public function __construct(public string $temporaryPath, public int $error, public int $size) {}
}
