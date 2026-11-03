<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\Request;
use Yinghua\Exceptions\ErrorCode;

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
            self::assertSame(ErrorCode::ValidationFailed, $error->errorCode);
            self::assertArrayHasKey($key, $error->fieldErrors);
        }
    }

    public function testNestedJsonListsAreStillPreserved(): void
    {
        self::assertSame(['items' => [['id' => 1]]], $this->request('{"items":[{"id":1}]}')->json());
    }

    /** @return iterable<string,array{string}> */
    public static function nonObjectBodies(): iterable
    {
        yield 'empty list' => ['[]'];
        yield 'list of objects' => ['[{"a":1}]'];
        yield 'string' => ['"x"'];
        yield 'number' => ['1'];
        yield 'broken' => ['{"a":'];
    }

    #[DataProvider('nonObjectBodies')]
    public function testTopLevelMustBeAnObject(string $body): void
    {
        try {
            $this->request($body)->json();
            self::fail('Non-object JSON body was accepted.');
        } catch (ApplicationError $error) {
            self::assertSame(ErrorCode::BadRequest, $error->errorCode);
        }
    }

    public function testEmptyObjectWithLeadingWhitespaceIsAccepted(): void
    {
        self::assertSame([], $this->request(" \n {}")->json());
    }

    private function request(string $body): Request
    {
        return new Request('POST', '/api/v1/example', [], ['content-type' => 'application/json'], [], $body, '127.0.0.1', 'test', 'test');
    }
}
