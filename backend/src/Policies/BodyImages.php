<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use DOMDocument;
use Yinghua\DTO\ContentUrl;
use Yinghua\Exceptions\ApplicationError;

/** 檢查淨化後正文裡的圖片；只看網址與屬性，不會去下載任何 URL。 */
final class BodyImages
{
    /** @return list<string> */
    public static function paths(string $html): array
    {
        if ($html === '') {
            return [];
        }
        $document = new DOMDocument();
        $old = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<meta charset="utf-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $paths = [];
            foreach ($document->getElementsByTagName('img') as $image) {
                if (trim($image->getAttribute('alt')) === '') {
                    throw ApplicationError::invalid('body', '正文每張圖片均須有替代文字。');
                }
                $path = $image->getAttribute('src');
                if (!preg_match('~^/media/[a-f0-9-]{36}\.(?:jpg|png|webp)$~D', $path)) {
                    ContentUrl::validate($path, 'body');
                }
                $paths[] = $path;
            }
            return array_values(array_unique($paths));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }
}
