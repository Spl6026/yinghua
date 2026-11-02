<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Adapters\LocalImageStorage;
use Yinghua\Config;
use Yinghua\DTO\UploadedImage;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\ImageOrigin;

/** 本機匯入圖片的路徑不能連帶放寬 HTTP 上傳的來源與圖片內容檢查。 */
class ImageOriginTest extends TestCase
{
    public function testLocalFileCannotMasqueradeAsHttpUpload(): void
    {
        $path = tempnam('/tmp', 'spl-image-origin-');
        self::assertIsString($path);
        try {
            file_put_contents($path, 'not-an-http-upload');
            $this->expectException(ApplicationError::class);
            (new LocalImageStorage(new Config()))->store(new UploadedImage($path, UPLOAD_ERR_OK, 18), '說明', '', '');
        } finally {
            unlink($path);
        }
    }

    public function testTrustedCliFileStillMustContainDecodableImage(): void
    {
        $path = tempnam('/tmp', 'spl-image-origin-');
        self::assertIsString($path);
        try {
            file_put_contents($path, '<script>not an image</script>');
            $this->expectException(ApplicationError::class);
            (new LocalImageStorage(new Config()))->store(new UploadedImage($path, UPLOAD_ERR_OK, 29, ImageOrigin::LocalCli), '說明', '', '');
        } finally {
            unlink($path);
        }
    }
}
