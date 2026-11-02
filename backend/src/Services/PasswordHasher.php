<?php

declare(strict_types=1);

namespace Yinghua\Services;

use SensitiveParameter;

/** 密碼原樣雜湊、不修剪前後空白；演算法使用 Argon2id。 */
class PasswordHasher
{
    private const array OPTIONS = ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1];
    private const string DUMMY_HASH = '$argon2id$v=19$m=65536,t=3,p=1$c29tZXNhbHRmb3J1bmtub3du$RIIEHcMvRWrnNWuTaX5flBGPPcbKaaIGfx8FnhyrjmA';

    public function hash(#[SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    public function verify(#[SensitiveParameter] string $password, ?string $hash): bool
    {
        $valid = password_verify($password, $hash ?? self::DUMMY_HASH);

        return $hash !== null && $valid;
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }
}
