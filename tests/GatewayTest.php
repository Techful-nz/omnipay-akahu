<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Tests;

use Omnipay\Akahu\Exception\InvalidPaymentStateException;
use Omnipay\Akahu\Exception\PaymentLimitExceededException;
use Omnipay\Akahu\Gateway;
use Omnipay\Akahu\Message\CompletePurchaseRequest;
use Omnipay\Akahu\Message\FetchConnectionsRequest;
use Omnipay\Akahu\Message\FetchTransactionRequest;
use Omnipay\Akahu\Message\PurchaseRequest;
use Omnipay\Akahu\Message\VoidRequest;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Http\Exception\RequestException;
use Omnipay\Tests\GatewayTestCase;

class GatewayTest extends GatewayTestCase
{
    /** @var Gateway */
    protected $gateway;

    private const PAYMENT_ID = 'one_off_payment_c01234567890123456789012345';

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->initialize([
            'appToken' => 'app_token_test',
            'appSecret' => 'secret_test',
            'payeeName' => 'Bilbo Baggins',
            'payeeAccountNumber' => '12-3456-7890123-00',
        ]);
    }

    public function testPurchaseRedirects(): void
    {
        $this->setMockHttpResponse('PurchaseSuccess.txt');

        $request = $this->gateway->purchase([
            'amount' => '25.25',
            'currency' => 'NZD',
            'returnUrl' => 'https://example.com/return',
            'notifyUrl' => 'https://example.com/webhook',
            'payeeReference' => 'INV-1234',
            'releaseAccountDetails' => true,
        ]);

        $this->assertInstanceOf(PurchaseRequest::class, $request);
        $response = $request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertSame('GET', $response->getRedirectMethod());
        $this->assertSame(
            'https://payments.akahu.nz/?payment=' . self::PAYMENT_ID,
            $response->getRedirectUrl(),
        );
        $this->assertSame(self::PAYMENT_ID, $response->getTransactionReference());

        $httpRequest = $this->getMockedRequests()[0];
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertSame('https://api.payments.akahu.io/v1/one-off-payments', (string) $httpRequest->getUri());
        $this->assertSame(
            'Basic ' . base64_encode('app_token_test:secret_test'),
            $httpRequest->getHeaderLine('Authorization'),
        );
        $this->assertSame([
            'amount' => 25.25,
            'redirect_uri' => 'https://example.com/return',
            'payee' => [
                'name' => 'Bilbo Baggins',
                'account_number' => '12-3456-7890123-00',
                'reference' => 'INV-1234',
            ],
            'webhook_uri' => 'https://example.com/webhook',
            'payer' => ['release_account_details' => true],
        ], json_decode((string) $httpRequest->getBody(), true));
    }

    public function testPurchaseFailure(): void
    {
        $this->setMockHttpResponse('PurchaseFailure.txt');

        $response = $this->gateway->purchase([
            'amount' => '10.00',
            'returnUrl' => 'https://example.com/return',
        ])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('Invalid payee', $response->getMessage());
        $this->assertSame('invalid_payee', $response->getCode());
        $this->assertSame(400, $response->getHttpStatusCode());
    }

    public function testCompletePurchaseSent(): void
    {
        $this->setMockHttpResponse('FetchSent.txt');

        $request = $this->gateway->completePurchase(['transactionReference' => self::PAYMENT_ID]);
        $this->assertInstanceOf(CompletePurchaseRequest::class, $request);
        $response = $request->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertTrue($response->isTerminal());
        $this->assertSame('SENT', $response->getStatus());
        $this->assertSame('25.50', $response->getAmount());
        $this->assertSame('Frodo Baggins', $response->getPayerName());
        $this->assertSame('01-2345-6789012-00', $response->getPayerAccountNumber());
        $this->assertSame(self::PAYMENT_ID, $response->getTransactionReference());

        $httpRequest = $this->getMockedRequests()[0];
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame(
            'https://api.payments.akahu.io/v1/one-off-payments/' . self::PAYMENT_ID,
            (string) $httpRequest->getUri(),
        );
    }

    public function testFetchTransactionPending(): void
    {
        $this->setMockHttpResponse('FetchSubmitted.txt');

        $request = $this->gateway->fetchTransaction(['transactionReference' => self::PAYMENT_ID]);
        $this->assertInstanceOf(FetchTransactionRequest::class, $request);
        $response = $request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertFalse($response->isTerminal());
        $this->assertSame('PENDING', $response->getCode());
        $this->assertSame('The payment was submitted to the bank', $response->getMessage());
    }

    public function testFetchTransactionCancelled(): void
    {
        $this->setMockHttpResponse('FetchCancelled.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => self::PAYMENT_ID])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertTrue($response->isCancelled());
        $this->assertSame('USER_CANCELLED', $response->getCode());
    }

    public function testFetchTransactionNotFound(): void
    {
        $this->setMockHttpResponse('FetchNotFound.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => self::PAYMENT_ID])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('not_found', $response->getCode());
        $this->assertSame('Payment not found', $response->getMessage());
    }

    public function testFetchTransactionRequiresReference(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->gateway->fetchTransaction()->send();
    }

    public function testVoidSuccess(): void
    {
        $this->setMockHttpResponse('VoidSuccess.txt');

        $request = $this->gateway->void(['transactionReference' => self::PAYMENT_ID]);
        $this->assertInstanceOf(VoidRequest::class, $request);
        $response = $request->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(self::PAYMENT_ID, $response->getTransactionReference());

        $httpRequest = $this->getMockedRequests()[0];
        $this->assertSame('PUT', $httpRequest->getMethod());
        $this->assertSame(
            'https://api.payments.akahu.io/v1/one-off-payments/' . self::PAYMENT_ID . '/cancel',
            (string) $httpRequest->getUri(),
        );
    }

    public function testVoidFailure(): void
    {
        $this->setMockHttpResponse('VoidFailure.txt');

        $response = $this->gateway->void(['transactionReference' => self::PAYMENT_ID])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('bad_payment_state', $response->getCode());
    }

    public function testErrorResponseByDefaultDoesNotThrow(): void
    {
        $this->setMockHttpResponse('PurchaseLimitExceeded.txt');

        $response = $this->gateway->purchase([
            'amount' => '50000.00',
            'returnUrl' => 'https://example.com/return',
        ])->send();

        $this->assertFalse($response->isRedirect());
        $this->assertInstanceOf(PaymentLimitExceededException::class, $response->getException());
        $this->assertSame('single_limit_exceeded', $response->getException()->getErrorCode());
    }

    public function testResponseReturnsTheSameException(): void
    {
        $this->setMockHttpResponse('FetchNotFound.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => self::PAYMENT_ID])->send();

        $this->assertSame($response->getException(), $response->getException());

        try {
            $response->throw();
            $this->fail('Expected a RequestException.');
        } catch (RequestException $e) {
            $this->assertSame($response->getException(), $e);
        }
    }

    public function testResponseThrow(): void
    {
        $this->setMockHttpResponse('FetchNotFound.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => self::PAYMENT_ID])->send();

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Akahu API request failed with HTTP 404: Payment not found');
        $response->throw();
    }

    public function testResponseThrowReturnsSuccessfulResponse(): void
    {
        $this->setMockHttpResponse('FetchCancelled.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => self::PAYMENT_ID])->send();

        // A cancelled payment is a valid answer from Akahu, not an API error.
        $this->assertNull($response->getException());
        $this->assertSame($response, $response->throw());
    }

    public function testThrowThrowsRequestExceptionForUnauthorized(): void
    {
        $this->setMockHttpResponse('Unauthorized.txt');

        try {
            $this->gateway->fetchTransaction(['transactionReference' => self::PAYMENT_ID])->send()->throw();
            $this->fail('Expected a RequestException.');
        } catch (RequestException $e) {
            $this->assertSame(RequestException::class, $e::class);
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
            $this->assertSame('GET', $e->getRequest()->getMethod());
            $this->assertStringEndsWith('/one-off-payments/' . self::PAYMENT_ID, (string) $e->getRequest()->getUri());
            $this->assertFalse($e->getRequest()->hasHeader('Authorization'));
        }
    }

    public function testThrowThrowsInvalidPaymentStateException(): void
    {
        $this->setMockHttpResponse('VoidFailure.txt');

        $this->expectException(InvalidPaymentStateException::class);
        $this->gateway->void(['transactionReference' => self::PAYMENT_ID])->send()->throw();
    }

    public function testThrowThrowsRequestExceptionForNonJsonBody(): void
    {
        $this->setMockHttpResponse('ServerError.txt');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('HTTP 500');
        $this->gateway->fetchConnections()->send()->throw();
    }

    public function testThrowReturnsSuccessfulResponse(): void
    {
        $this->setMockHttpResponse('PurchaseSuccess.txt');

        $response = $this->gateway->purchase([
            'amount' => '10.00',
            'returnUrl' => 'https://example.com/return',
        ])->send()->throw();

        $this->assertTrue($response->isRedirect());
    }

    public function testFetchConnections(): void
    {
        $this->setMockHttpResponse('ConnectionsSuccess.txt');

        $request = $this->gateway->fetchConnections();
        $this->assertInstanceOf(FetchConnectionsRequest::class, $request);
        $response = $request->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('westpac', $response->getConnections()[0]['bank']);
    }
}
