<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Config;
use Yinghua\Contracts\DatabaseHealthProbe;
use Yinghua\Controllers\HealthController;
use Yinghua\Http\Request;
use Yinghua\Services\ApplicationHealth;

class ApplicationHealthTest extends TestCase
{
    public function testDatabaseFailureIsSafeAndNeverIssuesCookies(): void
    {
        $probe = new class implements DatabaseHealthProbe {
            public function ready(): bool
            {
                throw new \RuntimeException('SECRET internal database host');
            }
        };
        $controller = new HealthController(new ApplicationHealth(new Config(), $probe));
        $response = $controller->handle($this->request('GET'));
        self::assertSame(503, $response->status);
        self::assertSame([], $response->cookies);
        self::assertSame('SERVICE_UNAVAILABLE', $response->payload['code']);
        self::assertStringNotContainsString('SECRET', json_encode($response->payload, JSON_THROW_ON_ERROR));
    }

    public function testWrongMethodDoesNotProbeDatabase(): void
    {
        $probe = new class implements DatabaseHealthProbe {
            public function ready(): bool
            {
                throw new \LogicException('Must not probe on POST');
            }
        };
        $response = (new HealthController(new ApplicationHealth(new Config(), $probe)))->handle($this->request('POST'));
        self::assertSame(405, $response->status);
        self::assertSame('GET, HEAD', $response->headers['Allow']);
    }

    public function testHeadAndGetHaveSameStatusAndReport(): void
    {
        $probe = new class implements DatabaseHealthProbe {
            public function ready(): bool
            {
                return true;
            }
        };
        $controller = new HealthController(new ApplicationHealth(new Config(), $probe));
        self::assertEquals($controller->handle($this->request('GET')), $controller->handle($this->request('HEAD')));
        self::assertSame(200, $controller->handle($this->request('GET'))->status);
    }

    private function request(string $method): Request
    {
        return new Request($method, '/api/v1/health', [], [], [], '', '127.0.0.1', 'test', 'unit-health');
    }
}
