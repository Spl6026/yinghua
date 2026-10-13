<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\Request;

class RequestBoundaryTest extends TestCase
{
    /** @return iterable<string,array{string}> */
    public static function numericKeys(): iterable
    {
        yield 'zero' => ['0'];
        yield 'positive integer' => ['42'];
        yield 'negative integer' => ['-1'];
    }

    #[DataProvider('numericKeys')]
    public function testNumericObjectKeysCannotDisappearBeforeTheInputWhitelist(string $key): void
    {
        $request = $this->request('{"name":"valid","' . $key . '":"unexpected"}');
        try {
            $request->json();
            self::fail('Unexpected key was silently ignored.');
        } catch (ApplicationError $error) {
            self::assertSame('VALIDATION_FAILED', $error->errorCode);
            self::assertArrayHasKey($key, $error->fieldErrors);
        }
    }

    public function testNestedJsonListsAreStillPreserved(): void
    {
        self::assertSame(['items' => [['id' => 1]]], $this->request('{"items":[{"id":1}]}')->json());
    }

    private function request(string $body): Request
    {
        return new Request('POST', '/api/v1/example', [], ['content-type' => 'application/json'], [], $body, '127.0.0.1', 'test', 'test');
    }
}
