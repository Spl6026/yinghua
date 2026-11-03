<?php

declare(strict_types=1);

namespace Yinghua\Exceptions;

use RuntimeException;

class ApplicationError extends RuntimeException
{
    /** @param array<string, string> $fieldErrors */
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly array $fieldErrors = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function invalid(string $field, string $message): self
    {
        return new self(ErrorCode::ValidationFailed, '請檢查輸入內容。', [$field => $message]);
    }

    public static function forbidden(): self
    {
        return new self(ErrorCode::Forbidden, '沒有執行此操作的權限。');
    }
}
