<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

readonly class FavoriteStatusInput
{
    /** @param list<string> $ids */
    public function __construct(public array $ids) {}

    /** @param array<string,mixed> $query */
    public static function fromArray(array $query): self
    {
        (new Input($query))->only(['ids']);
        $text = $query['ids'] ?? '';
        if (!is_string($text) || strlen($text) > 3700) {
            throw ApplicationError::invalid('ids', '請提供至多 100 個景點識別。');
        }
        $ids = $text === '' ? [] : explode(',', $text);
        if (count($ids) > 100) {
            throw ApplicationError::invalid('ids', '請提供至多 100 個景點識別。');
        }
        foreach ($ids as $id) {
            self::id($id);
        }

        return new self(array_values(array_unique($ids)));
    }

    public static function id(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $id)) {
            throw ApplicationError::invalid('attractionId', '無效的景點識別。');
        }

        return $id;
    }
}
