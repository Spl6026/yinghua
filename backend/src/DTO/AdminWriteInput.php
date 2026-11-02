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
        // 登入帳號只要求非空白，不額外限制開頭字元或最少字數。
        $account = mb_strtolower($input->text('account', 1, 60));
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
            $creating ? $input->password() : null,
            $creating ? null : $input->positiveInt('version'),
        );
    }
}
