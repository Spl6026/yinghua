<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\Csrf;
use Yinghua\Http\Request;
use Yinghua\Models\IdentityKind;
use Yinghua\Support\Secrets;

/** [E-29] 真實簽章與可控時鐘：有限回撥不誤拒，其他安全邊界仍拒絕。 */
class CsrfTest extends TestCase
{
    /** @return iterable<string,array{int,bool}> */
    public static function clockCases(): iterable
    {
        yield 'same second' => [0, true];
        yield 'one second backwards' => [-1, true];
        yield 'bounded backwards' => [-5, true];
        yield 'outside clock budget' => [-6, false];
        yield 'before expiry' => [3599, true];
        yield 'at expiry' => [3600, false];
    }

    #[DataProvider('clockCases')]
    public function testSignedTokenClockBoundary(int $offset, bool $allowed): void
    {
        $clock = new class implements Clock {
            public int $value = 2000000000;
            public function now(): int
            {
                return $this->value;
            }
        };
        $csrf = new Csrf(new Config(), $clock, new Secrets(new Config()));
        $kind = IdentityKind::Member;
        $initial = $csrf->rotate($kind);
        $token = $csrf->token($initial, $kind);
        $clock->value += $offset;
        $request = $this->request($token, $initial->cookieValue);
        if (!$allowed) {
            $this->expectException(ApplicationError::class);
        }
        $csrf->verify($request, $csrf->context($request, $kind), $kind);
        if ($allowed) {
            self::assertTrue(true);
        }
    }

    /** @return iterable<string,array{string}> */
    public static function invalidCases(): iterable
    {
        foreach (['signature','cookie','missing','origin','cross-site','namespace'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('invalidCases')]
    public function testRejectionBoundaries(string $case): void
    {
        $clock = new class implements Clock {
            public function now(): int
            {
                return 2000000000;
            }
        };
        $csrf = new Csrf(new Config(), $clock, new Secrets(new Config()));
        $context = $csrf->rotate(IdentityKind::Member);
        $token = $csrf->token($context, IdentityKind::Member);
        $token = match ($case) {
            'missing' => '', 'signature' => $token . '0', default => $token,
        };
        $request = $this->request(
            $token,
            $case === 'cookie' ? '' : $context->cookieValue,
            $case === 'origin' ? ['origin' => 'https://attacker.test'] : ($case === 'cross-site' ? ['sec-fetch-site' => 'cross-site'] : []),
        );
        $this->expectException(ApplicationError::class);
        $kind = $case === 'namespace' ? IdentityKind::Admin : IdentityKind::Member;
        $csrf->verify($request, $csrf->context($request, $kind), $kind);
    }

    /** @param array<string,string> $extra */
    private function request(string $token, string $cookie, array $extra = []): Request
    {
        return new Request(
            'POST',
            '/api/v1/auth/register',
            [],
            ['x-csrf-token' => $token, ...$extra],
            ['spl_member_anonymous' => $cookie],
            '{}',
            '127.0.0.1',
            'test',
            'unit-csrf',
        );
    }
}
