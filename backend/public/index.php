<?php

declare(strict_types=1);

use Yinghua\Composition;
use Yinghua\Config;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\Kernel;
use Yinghua\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$requestId = bin2hex(random_bytes(16));
try {
    $request = Request::capture();
    $requestId = $request->requestId;
    (new Composition(new Config()))->kernel()->handle($request)->emit($requestId, $request->method === 'HEAD');
} catch (ApplicationError $error) {
    Kernel::error($error)->emit($requestId);
} catch (Throwable $error) {
    error_log('request=' . $requestId . ' exception=' . $error::class);
    Kernel::error(new ApplicationError('INTERNAL_ERROR', '系統暫時無法完成操作，請稍後再試。'))->emit($requestId);
}
