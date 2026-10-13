<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\Models\Challenge;

class ChallengeRepository
{
    public function __construct(private readonly Database $database) {}

    public function create(string $id, string $tokenHash, string $mode, string $answerHash, string $binding, ?string $recipient, int $expires, int $maximum): void
    {
        $this->database->execute("UPDATE captcha_challenges SET revoked_at=UTC_TIMESTAMP() WHERE session_binding_hash=:binding AND purpose='register' AND revoked_at IS NULL AND used_at IS NULL", ['binding' => $binding]);
        $this->database->execute(
            "INSERT INTO captcha_challenges (id,token_hash,purpose,mode,answer_hmac,session_binding_hash,recipient_hash,max_attempts,expires_at) VALUES (:id,:token,'register',:mode,:answer,:binding,:recipient,:maximum,:expires)",
            ['id' => $id, 'token' => $tokenHash, 'mode' => $mode, 'answer' => $answerHash,
                'binding' => $binding, 'recipient' => $recipient, 'maximum' => $maximum, 'expires' => gmdate('Y-m-d H:i:s', $expires)],
        );
    }

    public function lock(string $token): ?Challenge
    {
        $record = $this->database->row("SELECT * FROM captcha_challenges WHERE token_hash=:token AND purpose='register' FOR UPDATE", ['token' => hash('sha256', $token)]);
        if ($record === null) {
            return null;
        }
        $row = new Row($record);

        return new Challenge(
            $row->string('id'),
            $row->string('mode'),
            $row->string('answer_hmac'),
            $row->string('session_binding_hash'),
            $row->nullableString('recipient_hash'),
            $row->integer('attempt_count'),
            $row->integer('max_attempts'),
            (int) strtotime($row->string('expires_at') . ' UTC'),
            $row->nullableString('used_at') !== null || $row->nullableString('revoked_at') !== null,
        );
    }

    public function failed(string $id): void
    {
        $this->database->execute('UPDATE captcha_challenges SET attempt_count=attempt_count+1 WHERE id=:id', ['id' => $id]);
    }

    public function consume(string $id): void
    {
        $this->database->execute('UPDATE captcha_challenges SET used_at=UTC_TIMESTAMP() WHERE id=:id', ['id' => $id]);
    }

    public function revoke(string $id): void
    {
        $this->database->execute('UPDATE captcha_challenges SET revoked_at=UTC_TIMESTAMP() WHERE id=:id', ['id' => $id]);
    }
}
