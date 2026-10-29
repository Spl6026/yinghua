<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

/** [E-29] 備份完整性與復原後憑證撤銷；不刪帳號、業務資料或 audit。 */
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
