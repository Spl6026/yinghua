<?php

declare(strict_types=1);

/** 一般 API 在啟動時就連不上資料庫，也要安全地回 503；這支腳本只能在隔離的測試環境執行。 */
if (getenv('APP_ENV') !== 'test' || getenv('DB_DATABASE') !== 'yinghua_test') {
    throw new RuntimeException('Named isolated environment required.');
}
putenv('DB_HOST=127.0.0.1');
$_SERVER['REQUEST_URI'] = '/api/v1/attractions';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require dirname(__DIR__) . '/public/index.php';
$raw = ob_get_clean();
if (!is_string($raw)) {
    throw new RuntimeException('Missing failure response.');
}
$payload = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
if (http_response_code() !== 503 || !is_array($payload) || ($payload['code'] ?? '') !== 'SERVICE_UNAVAILABLE'
    || array_keys($payload) !== ['code','message','fieldErrors','requestId']) {
    throw new RuntimeException('Connection failure boundary mismatch.');
}
echo "{\"checks\":2,\"passed\":true}\n";
