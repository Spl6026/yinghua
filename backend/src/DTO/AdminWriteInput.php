<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use SensitiveParameter;
use Yinghua\Exceptions\ApplicationError;

readonly class AdminWriteInput
{
    public function __construct(
        public string $account,
        public string $displayName,
        public string $email,
        public string $roleId,
        public string $status,
        #[SensitiveParameter]
        public ?string $password,
        public ?int $version,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values, bool $creating): self
    {
        $input = new Input($values);
        $input->only($creating
            ? ['account', 'displayName', 'email', 'roleId', 'status', 'password']
            : ['account', 'displayName', 'email', 'roleId', 'status', 'version']);
        $account = mb_strtolower($input->text('account', 3, 60));
        if (!preg_match('/^[a-z][a-z0-9._-]{2,59}$/D', $account)) {
            throw ApplicationError::invalid('account', '帳號須以英文字母開頭，使用英數、點、底線或連字號。');
        }
        $status = $input->text('status');
        if (!in_array($status, ['active', 'disabled'], true)) {
            throw ApplicationError::invalid('status', '不支援的帳號狀態。');
        }

        return new self(
            $account,
            $input->text('displayName', 1, 80),
            $input->email(),
            $input->text('roleId', 1, 40),
            $status,
            $creating ? $input->text('password', 15, 128, false) : null,
            $creating ? null : $input->positiveInt('version'),
        );
    }
}
