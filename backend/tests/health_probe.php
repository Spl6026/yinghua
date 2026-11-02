<?php

declare(strict_types=1);

/** 健康檢查的 CLI 故障注入入口：只接受測試環境，不輸出密碼或內部診斷資訊。 */
require dirname(__DIR__) . '/vendor/autoload.php';
if (getenv('APP_ENV') !== 'test') {
    throw new RuntimeException('Test environment required.');
}
$request = new \Yinghua\Http\Request('GET', '/api/v1/health', [], [], [], '', '', '', 'health-fixture');
$start = hrtime(true);
$response = (new \Yinghua\Composition(new \Yinghua\Config()))->health()->handle($request);
echo json_encode(['status' => $response->status, 'payload' => $response->payload,
    'elapsedMs' => (hrtime(true) - $start) / 1000000], JSON_THROW_ON_ERROR);
