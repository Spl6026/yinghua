<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\Exceptions\ApplicationError;

/** [S：G32／SEO E8–E9；E-28] 純驗證，不抓取外部網址。 */
class SeoUrlPolicy
{
    public static function https(string $value, string $field, bool $origin = false): string
    {
        if ($value === '') {
            return '';
        }
        $parts = parse_url($value);
        if ($parts === false || filter_var($value, FILTER_VALIDATE_URL) === false
            || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20<>"\\\\]/', $value)
            || ($origin && (isset($parts['query']) || !in_array($parts['path'] ?? '', ['', '/'], true)))) {
            throw ApplicationError::invalid($field, $origin ? '請填 HTTPS 網站根網址，不含路徑、參數或帳密。' : '請填有效的 HTTPS 圖片網址。');
        }

        return $origin ? rtrim($value, '/') : $value;
    }

    public static function path(string $path, string $field): void
    {
        if (!preg_match('~^/(?:[a-z0-9]+(?:-[a-z0-9]+)*/)*[a-z0-9]+(?:-[a-z0-9]+)*\.html$~D', $path)) {
            throw ApplicationError::invalid($field, '請選有效的站內 HTML 路徑。');
        }
    }
}
