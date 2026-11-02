<?php

declare(strict_types=1);

use Yinghua\Composition;
use Yinghua\Config;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\Kernel;
use Yinghua\Http\Request;
use Yinghua\Support\ErrorLog;

require dirname(__DIR__) . '/vendor/autoload.php';

$requestId = bin2hex(random_bytes(16));
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
    Kernel::error(new ApplicationError('INTERNAL_ERROR', '系統暫時無法完成操作，請稍後再試。'))->emit($requestId);
}
