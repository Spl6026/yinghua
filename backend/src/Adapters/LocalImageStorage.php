<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Yinghua\Config;
use Yinghua\Contracts\ImageStorage;
use Yinghua\DTO\MediaAsset;
use Yinghua\DTO\UploadedImage;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Support\Secrets;

/** [S：功能規格 G20/G29；E-18] 本地 volume adapter；驗證真實圖片並重新編碼。 */
class LocalImageStorage implements ImageStorage
{
    public function __construct(private readonly Config $config) {}

    public function store(UploadedImage $upload, string $alt, string $source, string $license): MediaAsset
    {
        if (in_array($upload->error, [UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE], true) || $upload->size > 8 * 1024 * 1024) {
            throw new ApplicationError('PAYLOAD_TOO_LARGE', '圖片不可超過 8 MiB。');
        }
        if ($upload->error !== UPLOAD_ERR_OK || !is_uploaded_file($upload->temporaryPath)) {
            throw ApplicationError::invalid('image', '請選擇可完整上傳的圖片。');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload->temporaryPath);
        $size = @getimagesize($upload->temporaryPath);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true) || $size === false || $size['mime'] !== $mime) {
            throw ApplicationError::invalid('image', '只接受有效 JPEG、PNG 或 WebP 圖片。');
        }
        [$width,$height] = $size;
        if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || $width * $height > 12000000) {
            throw ApplicationError::invalid('image', '圖片長邊不可超過 6000，總像素不可超過 1200 萬。');
        }
        $data = file_get_contents($upload->temporaryPath);
        $image = $data === false ? false : @imagecreatefromstring($data);
        if ($image === false) {
            throw ApplicationError::invalid('image', '圖片內容無法解碼。');
        }
        $id = Secrets::uuid();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg','image/png' => 'png',default => 'webp',
        };
        $key = $id . '.' . $extension;
        $path = $this->config->string('MEDIA_PATH', '/var/www/media') . '/' . $key;
        $temporary = $path . '.part';
        try {
            imagesavealpha($image, true);
            $success = match ($mime) {
                'image/jpeg' => imagejpeg($image, $temporary, 88),
                'image/png' => imagepng($image, $temporary, 6),
                default => imagewebp($image, $temporary, 85),
            };
            if (!$success || !rename($temporary, $path)) {
                throw new \RuntimeException('Cannot persist image.');
            }
            chmod($path, 0644);
            $bytes = filesize($path);
            if ($bytes === false) {
                throw new \RuntimeException('Cannot inspect image.');
            }

            return new MediaAsset($id, $key, $mime, $width, $height, $bytes, $alt, $source, $license);
        } catch (\Throwable $error) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            if (is_file($path)) {
                unlink($path);
            }
            throw $error;
        }
    }

    public function discard(MediaAsset $asset): void
    {
        $path = $this->config->string('MEDIA_PATH', '/var/www/media') . '/' . $asset->storageKey;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
