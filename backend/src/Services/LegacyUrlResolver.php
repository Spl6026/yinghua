<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\PublicCollection;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\LegacyUrlType;

/**
 * 景點、電影、消息與優惠共用的舊網址轉向。
 * 只讀成功發布的快照，確保轉到的網址在同一個版本裡一定存在；各類型的差異由 LegacyUrlType 提供。
 */
readonly class LegacyUrlResolver
{
    public function __construct(private ContentPublication $publication) {}

    /** 回傳目前的公開網址；找不到或目標不合法時一律回 NOT_FOUND，不透露原因。 */
    public function resolve(LegacyUrlType $type, string $original): string
    {
        return self::fromSnapshot($this->publication->read(), $type, $original);
    }

    /**
     * 比對規則與讀取快照分開，單元測試可以直接傳入快照。
     *
     * @param array<string,mixed> $snapshot
     */
    public static function fromSnapshot(array $snapshot, LegacyUrlType $type, string $original): string
    {
        $path = parse_url($original, PHP_URL_PATH);
        $other = is_string($path) ? $type->movedTo($path) : null;
        if ($other !== null && is_string($path)) {
            $moved = self::alias($snapshot, $other, $path);
            if (is_string($moved) && preg_match($other->canonicalPattern(), $moved)) {
                return $moved;
            }
        }
        $target = is_string($path) ? self::alias($snapshot, $type, $path) : null;
        if ($path === $type->legacyPage()) {
            $queryString = parse_url($original, PHP_URL_QUERY);
            parse_str(is_string($queryString) ? $queryString : '', $query);
            $id = $query['id'] ?? null;
            if (is_string($id)) {
                $legacy = $type->legacyId($id);
                foreach (PublicCollection::items($snapshot, $type->collectionKey()) as $item) {
                    $legacyId = $item['legacyId'] ?? null;
                    if (($item['id'] ?? null) === $id || (is_int($legacyId) && (string) $legacyId === $legacy)) {
                        $target = $item['url'] ?? null;
                        break;
                    }
                }
            }
        }
        // 對照表裡指向自己的項目代表網址沒變，不能回 301 造成無限轉向。
        if (!is_string($target) || $target === $path || !preg_match($type->canonicalPattern(), $target)) {
            throw new ApplicationError('NOT_FOUND', $type->notFoundMessage());
        }

        return $target;
    }

    /** @param array<string,mixed> $snapshot */
    private static function alias(array $snapshot, LegacyUrlType $type, string $path): mixed
    {
        $aliases = $snapshot[$type->aliasKey()] ?? [];

        return is_array($aliases) ? ($aliases[$path] ?? null) : null;
    }
}
