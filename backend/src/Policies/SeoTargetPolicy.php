<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\Input;
use Yinghua\DTO\SeoOverride;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\SeoResource;

/** SEO 個別設定的目標檢查：不支援萬用字元，只接受頁面清單中的明確路徑或內容 UUID。 */
readonly class SeoTargetPolicy
{
    /** @param list<string> $paths */
    public function __construct(private array $paths) {}

    /** @param array<string,mixed> $values
     * @return array{SeoResource,string}
     */
    public function parse(array $values): array
    {
        $input = new Input($values);
        $type = SeoResource::tryFrom($input->text('type', 1, 20));
        if ($type === null) {
            throw ApplicationError::invalid('target.type', '不支援的 SEO 內容類型。');
        }
        $input->only($type === SeoResource::Page ? ['type','path'] : ['type','id']);
        $target = $input->text($type === SeoResource::Page ? 'path' : 'id', 1, 180);
        if ($type === SeoResource::Page
            ? !in_array($target, $this->paths, true)
            : !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $target)) {
            throw ApplicationError::invalid('target', '請選擇有效頁面或內容識別。');
        }

        return [$type, $target];
    }

    public function assertCanonical(SeoOverride $override): void
    {
        if ($override->canonicalPath === null) {
            return;
        }
        SeoUrlPolicy::path($override->canonicalPath, 'canonicalPath');
        // 個別內容的 canonical 由穩定 UUID 解析最新 slug，不允許跨內容指定。
        if ($override->type !== SeoResource::Page || str_contains($override->target, '*')
            || $override->canonicalPath !== $override->target) {
            throw ApplicationError::invalid('canonicalPath', '此目標使用正式內容自身網址，請留空繼承。');
        }
    }
}
