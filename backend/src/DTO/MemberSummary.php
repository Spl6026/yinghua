<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 會員列表只需要的欄位，不碰密碼或驗證相關資料。 */
readonly class MemberSummary
{
    public function __construct(
        public string $memberId,
        public string $displayName,
        public string $email,
        public string $status,
        public string $joinedAt,
    ) {}

    public static function mask(string $text): string
    {
        return $text === '' ? '＊' : mb_substr($text, 0, 1) . '＊＊＊';
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        $parts = explode('@', $this->email, 2);
        return ['memberId' => $this->memberId, 'displayName' => self::mask($this->displayName),
            'email' => self::mask($parts[0]) . '@' . self::mask($parts[1] ?? ''),
            'status' => $this->status, 'joinedAt' => $this->joinedAt];
    }
}
