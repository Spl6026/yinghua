<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\MediaRepository;

/** [E-18] 草稿媒體僅授權管理員可預覽；公開媒體須被目前發布版本使用。 */
class MediaAccess
{
    public function __construct(private readonly ContentPublication $publication, private readonly Authentication $authentication, private readonly Authorization $authorization, private readonly MediaRepository $media) {}

    public function allows(string $path, string $token): bool
    {
        if (!preg_match('~^/media/[a-f0-9-]{36}\.(?:jpg|png|webp)$~D', $path)) {
            return false;
        }
        try {
            $snapshot = $this->publication->read();
            $places = $snapshot['items'] ?? [];
            $films = $snapshot['films'] ?? [];
            $items = [...(is_array($places) ? $places : []), ...(is_array($films) ? $films : [])];
            foreach ($items as $item) {
                if (is_array($item) && ($item['image'] ?? null) === $path) {
                    return true;
                }
            }
        } catch (ApplicationError) {
            // 公開版本尚未完成時，仍可由有權管理員預覽上傳。
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
                if ($right['module'] === $module->value && in_array($right['action'], ['view','create','edit'], true) && $right['allowed']) {
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
