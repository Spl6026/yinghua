<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

/**
 * 清除已過期的登入、驗證碼、密碼重設與限速紀錄。
 *
 * 這些資料只在有效期間內有用，但未登入的嘗試（登入失敗、索取驗證碼）每次都會新增一列，
 * 不清除的話資料表會無限成長。登入事件（auth_events）屬於稽核紀錄，保存期限另行決定，這裡不處理。
 */
class SecurityRecordRepository
{
    /** 每批刪除的列數上限，避免一次刪太多而長時間鎖住正在使用的資料表。 */
    private const BATCH = 1000;

    /** 過期後再保留一天，方便追查剛發生的問題。 */
    private const GRACE_SECONDS = 86400;

    private const TABLES = ['sessions', 'captcha_challenges', 'password_reset_tokens', 'auth_rate_limits'];

    public function __construct(private readonly Database $database) {}

    /** @return array<string,int> 各資料表刪除的列數 */
    public function pruneExpired(int $now): array
    {
        $cutoff = gmdate('Y-m-d H:i:s', $now - self::GRACE_SECONDS);
        $removed = [];
        foreach (self::TABLES as $table) {
            $removed[$table] = 0;
            do {
                $count = $this->database->execute(
                    'DELETE FROM ' . $table . ' WHERE expires_at < :cutoff ORDER BY expires_at LIMIT ' . self::BATCH,
                    ['cutoff' => $cutoff],
                )->rowCount();
                $removed[$table] += $count;
            } while ($count === self::BATCH);
        }

        return $removed;
    }
}
