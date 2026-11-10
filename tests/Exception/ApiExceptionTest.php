<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Tests\Exception;

use GuzzleHttp\Psr7\Request;
use Omnipay\Akahu\Exception\AkahuException;
use Omnipay\Akahu\Exception\ApiException;
use Omnipay\Akahu\Exception\InvalidPaymentStateException;
use Omnipay\Akahu\Exception\PaymentLimitExceededException;
use Omnipay\Common\Exception\OmnipayException;
use Omnipay\Common\Http\Exception\RequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApiExceptionTest extends TestCase
{
    private static function body(string $code): array
    {
        return ['success' => false, 'message' => "Error: $code", 'issues' => [['code' => $code, 'path' => []]]];
    }

    private static function request(): Request
    {
        return new Request('POST', 'https://api.payments.akahu.io/v1/one-off-payments');
    }

    public static function akahuErrors(): array
    {
        return [
            'single limit' => [self::body('single_limit_exceeded'), PaymentLimitExceededException::class],
            'bank limit' => [self::body('bank_limit_exceeded'), PaymentLimitExceededException::class],
            'bad payment state' => [self::body('bad_payment_state'), InvalidPaymentStateException::class],
        ];
    }

    #[DataProvider('akahuErrors')]
    public function testKnownErrorCodesGetTheirOwnException(array $body, string $class): void
    {
        $request = self::request();
        $exception = ApiException::fromResponse($request, 400, $body);

        $this->assertSame($class, $exception::class);
        $this->assertInstanceOf(AkahuException::class, $exception);
        $this->assertInstanceOf(OmnipayException::class, $exception);
        $this->assertInstanceOf(RequestException::class, $exception);
        $this->assertSame($request, $exception->getRequest());
        $this->assertSame(400, $exception->getHttpStatusCode());
        $this->assertSame(400, $exception->getCode());
    }

    public static function otherErrors(): array
    {
        return [
            'invalid_payee' => [400, self::body('invalid_payee')],
            'unauthorized' => [401, self::body('unauthorized')],
            'not found' => [404, self::body('not_found')],
            'rate limited' => [429, []],
            'server error' => [500, []],
        ];
    }

    #[DataProvider('otherErrors')]
    public function testOtherErrorsAreClientRequestExceptions(int $status, array $body): void
    {
        $request = self::request();
        $exception = ApiException::fromResponse($request, $status, $body);

        $this->assertSame(RequestException::class, $exception::class);
        $this->assertSame($request, $exception->getRequest());
        $this->assertStringContainsString("HTTP $status", $exception->getMessage());
    }

    public function testOtherErrorMessageIncludesAkahuMessage(): void
    {
        $exception = ApiException::fromResponse(self::request(), 401, ['message' => 'Unauthorized']);

        $this->assertSame('Akahu API request failed with HTTP 401: Unauthorized', $exception->getMessage());
    }

    public function testDefaultMessageWithoutBody(): void
    {
        $exception = ApiException::fromResponse(self::request(), 503, []);

        $this->assertSame('Akahu API request failed with HTTP 503.', $exception->getMessage());
    }

    public function testExposesErrorDetails(): void
    {
        $body = [
            'success' => false,
            'message' => 'Amount too large',
            'issues' => [
                ['code' => 'single_limit_exceeded', 'message' => 'Over the limit', 'path' => ['amount']],
                ['code' => 'bank_limit_exceeded', 'message' => 'Over the bank limit', 'path' => ['amount']],
            ],
        ];

        $exception = ApiException::fromResponse(self::request(), 400, $body);

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame('Amount too large', $exception->getMessage());
        $this->assertSame('single_limit_exceeded', $exception->getErrorCode());
        $this->assertSame(['single_limit_exceeded', 'bank_limit_exceeded'], $exception->getErrorCodes());
        $this->assertSame(['amount'], $exception->getIssues()[0]['path']);
        $this->assertSame($body, $exception->getResponseBody());
    }
}
