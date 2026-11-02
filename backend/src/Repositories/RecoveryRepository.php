<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

/** 檢查備份完整性，並在復原後撤銷既有憑證；不刪除帳號、業務資料或稽核紀錄。 */
readonly class RecoveryRepository
{
    public function __construct(private Database $database) {}

    /** @return list<string> */
    public function mediaKeys(): array
    {
        return array_map(
            static fn(array $row): string => (new Row($row))->string('storage_key'),
            $this->database->rows('SELECT storage_key FROM media_assets ORDER BY storage_key'),
        );
    }

    public function revokeRestoredCredentials(): void
    {
        foreach (['sessions','password_reset_tokens','captcha_challenges'] as $table) {
            $this->database->execute('UPDATE ' . $table . ' SET revoked_at=UTC_TIMESTAMP() WHERE revoked_at IS NULL');
        }
    }
}
