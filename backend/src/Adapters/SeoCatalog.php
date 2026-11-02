<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use RuntimeException;
use Yinghua\Policies\SeoTargetPolicy;

/** PHP 與 Node 共用的頁面 SEO 目錄；只讀取明確列出的既有頁面，不讀使用者上傳的檔案。 */
readonly class SeoCatalog
{
    /** @var list<array{path:string,label:string,title:string,description:string,protected:bool,template:bool}> */
    public array $pages;

    public function __construct(string $source)
    {
        $json = file_get_contents($source . '/resources/seo-pages.json');
        $decoded = json_decode($json === false ? '' : $json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new RuntimeException('Invalid SEO catalog.');
        }
        $pages = [];
        foreach ($decoded as $value) {
            if (!is_array($value) || !is_string($value['path'] ?? null) || !is_string($value['label'] ?? null)
                || !is_string($value['title'] ?? null) || !is_string($value['description'] ?? null)
                || !is_bool($value['protected'] ?? null) || !is_bool($value['template'] ?? null)) {
                throw new RuntimeException('Invalid SEO page.');
            }
            $pages[] = [
                'path' => $value['path'],
                'label' => $value['label'],
                'title' => $value['title'],
                'description' => $value['description'],
                'protected' => $value['protected'],
                'template' => $value['template'],
            ];
        }
        $this->pages = $pages;
    }

    public function policy(): SeoTargetPolicy
    {
        return new SeoTargetPolicy(array_column($this->pages, 'path'));
    }
}
