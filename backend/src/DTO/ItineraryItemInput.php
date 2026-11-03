<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Support\Uuid;
use Yinghua\Exceptions\ApplicationError;

/** 加入或移除目前行程站點的輸入；version=0 代表會員還沒有目前行程。名稱、日期等編排走完整行程 API。 */
readonly class ItineraryItemInput
{
    /**
     * @param ?string $itineraryId 用戶端看到的目前行程；version 大於 0 時必填，
     *   用來確認目前行程沒有被切換成另一份
     */
    public function __construct(public int $version, public ?string $itineraryId = null) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        (new Input($values))->only(['version', 'itineraryId']);
        $version = $values['version'] ?? null;
        if (!is_int($version) || $version < 0 || $version > 4294967295) {
            throw ApplicationError::invalid('version', '請提供有效行程版本。');
        }
        $itineraryId = $values['itineraryId'] ?? null;
        if ($itineraryId !== null && (!is_string($itineraryId) || !Uuid::isValid($itineraryId))) {
            throw ApplicationError::invalid('itineraryId', '行程編號格式不正確。');
        }
        // 已有行程時必須說明是哪一份：版本號只在單一行程內遞增，換了目前行程後單靠版本會誤判。
        if ($version > 0 && $itineraryId === null) {
            throw ApplicationError::invalid('itineraryId', '請提供目前行程的編號。');
        }

        return new self($version, $itineraryId);
    }
}
