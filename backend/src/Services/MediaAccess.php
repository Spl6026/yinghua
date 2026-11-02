<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\MediaRepository;

/** 草稿中的媒體只有具權限的管理員能預覽；公開存取的媒體必須是目前發布版本實際用到的。 */
class MediaAccess
{
    public function __construct(
        private readonly ContentPublication $publication,
        private readonly Authentication $authentication,
        private readonly Authorization $authorization,
        private readonly MediaRepository $media,
    ) {}

    public function allows(string $path, string $token): bool
    {
        if (!preg_match('~^/media/[a-f0-9-]{36}\.(?:jpg|png|webp)$~D', $path)) {
            return false;
        }
        try {
            $snapshot = $this->publication->read();
            $places = $snapshot['items'] ?? [];
            $films = $snapshot['films'] ?? [];
            $news = $snapshot['news'] ?? [];
            $offers = $snapshot['offers'] ?? [];
            $items = [...(is_array($places) ? $places : []),
                ...(is_array($films) ? $films : []),
                ...(is_array($news) ? $news : []),
                ...(is_array($offers) ? $offers : [])];
            foreach ($items as $item) {
                if (is_array($item) && ($item['image'] ?? null) === $path) {
                    return true;
                }
                if (is_array($item) && is_array($item['bodyImages'] ?? null) && in_array($path, $item['bodyImages'], true)) {
                    return true;
                }
            }
        } catch (ApplicationError) {
            // 公開版本還沒產生好時，具權限的管理員仍可預覽剛上傳的檔案。
        }
        $module = $this->media->moduleForStorage(substr($path, strlen('/media/')));
        if ($module === null) {
            return false;
        }
        try {
            $identity = $this->authentication->current(IdentityKind::Admin, $token);
            $actor = $identity->effective();
            if (!$identity->previewAvailable()) {
                return false;
            }
            foreach ($this->authorization->permissions($actor) as $right) {
                if ($right['module'] === $module->value
                    && in_array($right['action'], ['view','create','edit'], true) && $right['allowed']) {
                    $this->authorization->require($actor, $module->value, $right['action']);

                    return true;
                }
            }
        } catch (ApplicationError) {
            return false;
        }

        return false;
    }
}
