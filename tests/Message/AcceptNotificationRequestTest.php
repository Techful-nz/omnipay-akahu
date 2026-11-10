<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Tests\Message;

use GuzzleHttp\Psr7\Response;
use Omnipay\Akahu\Exception\InvalidWebhookException;
use Omnipay\Akahu\Message\AcceptNotificationRequest;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Http\Exception\RequestException;
use Omnipay\Common\Message\NotificationInterface;
use Omnipay\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class AcceptNotificationRequestTest extends TestCase
{
    private static string $privateKey;
    private static string $publicKey;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        self::$privateKey = $private;
        self::$publicKey = openssl_pkey_get_details($key)['key'];
    }

    private function makeRequest(string $body, ?string $signature, string $keyId = 'key_1'): AcceptNotificationRequest
    {
        $httpRequest = new HttpRequest([], [], [], [], [], [
            'HTTP_X_AKAHU_PAYMENTS_SIGNATURE' => $signature,
            'HTTP_X_AKAHU_PAYMENTS_SIGNING_KEY' => $keyId,
        ], $body);

        $request = new AcceptNotificationRequest($this->getHttpClient(), $httpRequest);
        $request->initialize(['appToken' => 'app_token_test', 'appSecret' => 'secret_test']);

        return $request;
    }

    private function sign(string $body): string
    {
        openssl_sign($body, $signature, self::$privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function body(string $status, ?string $reason = null): string
    {
        return json_encode(array_filter([
            'type' => 'ONE_OFF_PAYMENT_STATUS_UPDATE',
            '_payment' => 'one_off_payment_c01234567890123456789012345',
            'status' => $status,
            'status_reason' => $reason ? ['code' => $reason, 'message' => 'Reason'] : null,
            'emitted_at' => '2026-11-05T03:05:00.000Z',
        ]));
    }

    private function makeExplicitRequest(array $parameters): AcceptNotificationRequest
    {
        // An empty HTTP request: everything must come from the parameters.
        $request = new AcceptNotificationRequest($this->getHttpClient(), new HttpRequest());
        $request->initialize($parameters + [
            'appToken' => 'app_token_test',
            'appSecret' => 'secret_test',
            'webhookPublicKeys' => ['key_1' => self::$publicKey],
        ]);

        return $request;
    }

    public function testExplicitParameters(): void
    {
        $body = $this->body('SENT');

        $notification = $this->makeExplicitRequest([
            'body' => $body,
            'signature' => $this->sign($body),
            'signingKey' => 'key_1',
        ])->send();

        $this->assertTrue($notification->isSuccessful());
        $this->assertSame('one_off_payment_c01234567890123456789012345', $notification->getTransactionReference());
    }

    public function testExplicitParametersOverrideHttpRequest(): void
    {
        $body = $this->body('SENT');
        $request = $this->makeRequest('{"tampered":true}', 'bm90LWEtc2lnbmF0dXJl', 'other_key');
        $request->initialize([
            'appToken' => 'app_token_test',
            'appSecret' => 'secret_test',
            'webhookPublicKeys' => ['key_1' => self::$publicKey],
            'body' => $body,
            'signature' => $this->sign($body),
            'signingKey' => 'key_1',
        ]);

        $this->assertSame('SENT', $request->send()->getStatus());
    }

    public function testExplicitParametersMissingSignature(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->makeExplicitRequest(['body' => $this->body('SENT'), 'signingKey' => 'key_1'])->send();
    }

    public function testValidSignatureWithSuppliedKey(): void
    {
        $body = $this->body('SENT');
        $request = $this->makeRequest($body, $this->sign($body));
        $request->setWebhookPublicKeys(['key_1' => self::$publicKey]);

        $notification = $request->send();

        $this->assertTrue($notification->isSuccessful());
        $this->assertSame(NotificationInterface::STATUS_COMPLETED, $notification->getTransactionStatus());
        $this->assertSame('one_off_payment_c01234567890123456789012345', $notification->getTransactionReference());
        $this->assertSame('ONE_OFF_PAYMENT_STATUS_UPDATE', $notification->getType());
        $this->assertSame('2026-11-05T03:05:00.000Z', $notification->getEmittedAt());
        $this->assertCount(0, $this->getMockedRequests());
    }

    public function testFetchesAndCachesPublicKey(): void
    {
        $this->getMockClient()->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'keyId' => 'key_fetch',
            'publicKey' => self::$publicKey,
        ])));

        $body = $this->body('FAILED', 'REJECTED');

        $notification = $this->makeRequest($body, $this->sign($body), 'key_fetch')->send();
        $this->assertSame(NotificationInterface::STATUS_FAILED, $notification->getTransactionStatus());
        $this->assertSame('REJECTED', $notification->getCode());

        // Second webhook with the same key uses the cache: no new HTTP request.
        $this->makeRequest($body, $this->sign($body), 'key_fetch')->send();

        $requests = $this->getMockedRequests();
        $this->assertCount(1, $requests);
        $this->assertSame('https://api.payments.akahu.io/v1/webhooks/keys/key_fetch', (string) $requests[0]->getUri());
    }

    public function testUsesPsr16CacheForPublicKeys(): void
    {
        $cache = new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore());
        $this->getMockClient()->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'keyId' => 'key_psr16',
            'publicKey' => self::$publicKey,
        ])));

        $body = $this->body('SENT');
        $request = $this->makeRequest($body, $this->sign($body), 'key_psr16');
        $request->setWebhookKeyCache($cache)->setWebhookKeyCacheTtl(60);

        $this->assertTrue($request->send()->isSuccessful());
        $this->assertSame(
            self::$publicKey,
            $cache->get('akahu_webhook_key_' . sha1('https://api.payments.akahu.io/v1|key_psr16')),
        );
    }

    public function testReadsPublicKeyFromPsr16Cache(): void
    {
        $cache = new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore());
        $cache->set('akahu_webhook_key_' . sha1('https://api.payments.akahu.io/v1|key_cached'), self::$publicKey);

        $body = $this->body('SENT');
        $request = $this->makeRequest($body, $this->sign($body), 'key_cached');
        $request->setWebhookKeyCache($cache);

        $this->assertTrue($request->send()->isSuccessful());
        $this->assertCount(0, $this->getMockedRequests());
    }

    public function testPendingStatus(): void
    {
        $body = $this->body('SUBMITTED', 'PENDING');
        $request = $this->makeRequest($body, $this->sign($body));
        $request->setWebhookPublicKeys(['key_1' => self::$publicKey]);

        $this->assertSame(NotificationInterface::STATUS_PENDING, $request->send()->getTransactionStatus());
    }

    public function testUnknownSigningKeyIsRejected(): void
    {
        $this->getMockClient()->addResponse(new Response(404, ['Content-Type' => 'application/json'], json_encode([
            'success' => false,
            'message' => 'Not found',
            'issues' => [['code' => 'not_found']],
        ])));

        $body = $this->body('SENT');

        $this->expectException(InvalidWebhookException::class);
        $this->makeRequest($body, $this->sign($body), 'key_unknown')->send();
    }

    public function testKeyFetchAuthFailureThrowsRequestException(): void
    {
        $body = $this->body('SENT');
        $request = $this->makeRequest($body, $this->sign($body), 'key_auth_fail');
        $this->setMockHttpResponse('Unauthorized.txt');

        // Not an InvalidWebhookException: the webhook may be fine, so the app
        // should reply 5xx and let Akahu retry once credentials are fixed.
        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('HTTP 401');
        $request->send();
    }

    public function testTamperedBodyIsRejected(): void
    {
        $signature = $this->sign($this->body('FAILED'));
        $request = $this->makeRequest($this->body('SENT'), $signature);
        $request->setWebhookPublicKeys(['key_1' => self::$publicKey]);

        $this->expectException(InvalidWebhookException::class);
        $request->send();
    }

    public function testMissingSignatureIsRejected(): void
    {
        $request = $this->makeRequest($this->body('SENT'), null);
        $request->setWebhookPublicKeys(['key_1' => self::$publicKey]);

        $this->expectException(InvalidWebhookException::class);
        $request->send();
    }
}
