<?php

declare(strict_types=1);

use Yinghua\Composition;
use Yinghua\Config;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\Kernel;
use Yinghua\Http\Request;
use Yinghua\Support\ErrorLog;
use Yinghua\Exceptions\ErrorCode;

require dirname(__DIR__) . '/vendor/autoload.php';

$requestId = bin2hex(random_bytes(16));
// 記憶體不足等致命錯誤不會進入下面的 catch，PHP 只會回空白的 500；
// 這裡在請求結束時補上與其他 API 錯誤相同格式的 JSON，前端才能顯示可理解的訊息。
register_shutdown_function(static function () use (&$requestId): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        return;
    }
    error_log(sprintf('request=%s fatal type=%d at=%s:%d', $requestId, $error['type'], basename($error['file']), $error['line']));
    if (!headers_sent()) {
        Kernel::error(new ApplicationError(ErrorCode::InternalError, '系統暫時無法完成操作，請稍後再試。'))->emit($requestId);
    }
});
try {
    $request = Request::capture();
    $requestId = $request->requestId;
    $composition = new Composition(new Config());
    $response = $request->path === Composition::HEALTH_PATH
        ? $composition->health()->handle($request)
        : $composition->kernel()->handle($request);
    $response->emit($requestId, $request->method === 'HEAD');
} catch (ApplicationError $error) {
    Kernel::error($error)->emit($requestId);
} catch (Throwable $error) {
    ErrorLog::exception('request=' . $requestId, $error);
    Kernel::error(new ApplicationError(ErrorCode::InternalError, '系統暫時無法完成操作，請稍後再試。'))->emit($requestId);
}
