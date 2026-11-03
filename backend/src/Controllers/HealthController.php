<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Http\Request;
use Yinghua\Http\Response;
use Yinghua\Services\ApplicationHealth;
use Yinghua\Exceptions\ErrorCode;

readonly class HealthController
{
    public function __construct(private ApplicationHealth $health) {}

    public function handle(Request $request): Response
    {
        if (!in_array($request->method, ['GET','HEAD'], true)) {
            return new Response(405, ['code' => ErrorCode::MethodNotAllowed->value, 'message' => '不支援此操作方式。',
                'fieldErrors' => (object) []], headers: ['Allow' => 'GET, HEAD']);
        }
        $report = $this->health->inspect();

        return new Response($report->ready ? 200 : 503, $report->toArray());
    }
}
