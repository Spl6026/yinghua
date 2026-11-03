<?php

declare(strict_types=1);

namespace Yinghua\Support;

use PDOException;
use Throwable;

/**
 * 未預期的例外統一由這裡寫進錯誤紀錄。
 *
 * 只記類別、錯誤代碼、發生位置與前幾層呼叫位置，足夠讓開發者找到出錯的程式；
 * 刻意不記例外訊息與參數：資料庫的重複鍵訊息會帶出電子郵件等欄位值，參數也可能含密碼或 token。
 */
final class ErrorLog
{
    private const TRACE_FRAMES = 6;
    private const MAX_CAUSES = 3;

    public static function exception(string $context, Throwable $error): void
    {
        error_log($context . ' ' . self::describe($error));
    }

    public static function describe(Throwable $error): string
    {
        $parts = [];
        $current = $error;
        for ($depth = 0; $current !== null && $depth < self::MAX_CAUSES; $depth++) {
            $parts[] = sprintf(
                'exception=%s code=%s at=%s trace=%s',
                $current::class,
                self::code($current),
                self::location($current->getFile(), $current->getLine()),
                self::trace($current),
            );
            $current = $current->getPrevious();
        }

        return implode(' caused_by ', $parts);
    }

    /** PDO 例外的 SQLSTATE 與資料庫錯誤編號（例如 1062 重複鍵）最能看出問題類型。 */
    private static function code(Throwable $error): string
    {
        if ($error instanceof PDOException && is_array($error->errorInfo)) {
            $state = $error->errorInfo[0] ?? null;
            $driverCode = $error->errorInfo[1] ?? null;

            return (is_scalar($state) ? (string) $state : (string) $error->getCode())
                . '/' . (is_scalar($driverCode) ? (string) $driverCode : '-');
        }

        return (string) $error->getCode();
    }

    private static function trace(Throwable $error): string
    {
        $frames = [];
        foreach (array_slice($error->getTrace(), 0, self::TRACE_FRAMES) as $frame) {
            $function = (isset($frame['class']) ? $frame['class'] . ($frame['type'] ?? '::') : '') . $frame['function'];
            $frames[] = $function . (isset($frame['file']) ? '@' . self::location($frame['file'], $frame['line'] ?? 0) : '');
        }

        return $frames === [] ? '-' : implode('<', $frames);
    }

    /** 只留專案內的相對路徑，紀錄比較短也不透露主機目錄結構。 */
    private static function location(string $file, int $line): string
    {
        $root = dirname(__DIR__, 2) . '/';

        return (str_starts_with($file, $root) ? substr($file, strlen($root)) : basename($file)) . ':' . $line;
    }
}
