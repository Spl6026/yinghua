<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** 會員清單的分頁游標；游標只表示位置，不代表授權，Repository 查詢時仍要加上會員條件。 */
readonly class MemberPageInput
{
    /** @param array<string,mixed> $position */
    public function __construct(public int $pageSize, public array $position = []) {}

    /** @param array<string,mixed> $query */
    public static function fromArray(array $query, int $defaultSize = 20): self
    {
        (new Input($query))->only(['cursor', 'pageSize']);
        $size = $query['pageSize'] ?? (string) $defaultSize;
        if (!is_string($size) || !ctype_digit($size) || strlen($size) > 3 || (int) $size < 1 || (int) $size > 100) {
            throw ApplicationError::invalid('pageSize', '每頁筆數須為 1–100。');
        }
        $cursor = $query['cursor'] ?? '';
        if (!is_string($cursor) || strlen($cursor) > 1024 || !preg_match('/^[a-zA-Z0-9_-]*$/D', $cursor)) {
            throw ApplicationError::invalid('cursor', '無效的分頁位置。');
        }
        if ($cursor === '') {
            return new self((int) $size);
        }
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        try {
            $value = $decoded === false ? null : json_decode($decoded, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $value = null;
        }
        if (!is_array($value) || array_is_list($value)) {
            throw ApplicationError::invalid('cursor', '無效的分頁位置。');
        }
        $position = [];
        foreach ($value as $key => $entry) {
            if (!is_string($key)) {
                throw ApplicationError::invalid('cursor', '無效的分頁位置。');
            }
            $position[$key] = $entry;
        }

        return new self((int) $size, $position);
    }

    /** @param array<string,int|string> $position */
    public static function cursor(array $position): string
    {
        return rtrim(strtr(base64_encode(json_encode($position, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{at:string,id:string}|null */
    public function favoritePosition(): ?array
    {
        if ($this->position === []) {
            return null;
        }
        try {
            $input = new Input($this->position);
            $input->only(['at', 'id']);
            $at = $input->text('at', 19, 19);
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $at, new \DateTimeZone('UTC'));
            if ($date === false || $date->format('Y-m-d H:i:s') !== $at) {
                throw ApplicationError::invalid('cursor', '無效的分頁位置。');
            }

            return ['at' => $at, 'id' => FavoriteStatusInput::id($input->text('id', 36, 36))];
        } catch (ApplicationError) {
            throw ApplicationError::invalid('cursor', '無效的分頁位置。');
        }
    }

    /** @return array{id:string,version:int,order:int}|null */
    public function itineraryPosition(): ?array
    {
        if ($this->position === []) {
            return null;
        }
        try {
            $input = new Input($this->position);
            $input->only(['id', 'version', 'order']);

            return ['id' => FavoriteStatusInput::id($input->text('id', 36, 36)),
                'version' => $input->positiveInt('version'), 'order' => $input->positiveInt('order')];
        } catch (ApplicationError) {
            throw ApplicationError::invalid('cursor', '無效的行程分頁位置。');
        }
    }
}
