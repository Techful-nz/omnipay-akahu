<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Tests\Laravel;

use Illuminate\Http\Request;
use Omnipay\Akahu\Gateway;
use Omnipay\Akahu\Laravel\AkahuServiceProvider;
use Orchestra\Testbench\TestCase;
use Psr\SimpleCache\CacheInterface;

class AkahuServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AkahuServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('akahu.app_token', 'app_token_test');
        $app['config']->set('akahu.app_secret', 'secret_test');
        $app['config']->set('akahu.payee_name', 'Acme Ltd');
        $app['config']->set('akahu.payee_account_number', '12-3456-7890123-00');
        $app['config']->set('cache.default', 'array');
    }

    public function testGatewayIsConfiguredFromConfig(): void
    {
        $gateway = $this->app->make(Gateway::class);

        $this->assertSame('app_token_test', $gateway->getAppToken());
        $this->assertSame('secret_test', $gateway->getAppSecret());
        $this->assertSame('Acme Ltd', $gateway->getPayeeName());
        $this->assertSame('12-3456-7890123-00', $gateway->getPayeeAccountNumber());
        $this->assertInstanceOf(CacheInterface::class, $gateway->getWebhookKeyCache());
        $this->assertSame(86400, $gateway->getWebhookKeyCacheTtl());
        $this->assertSame($gateway, $this->app->make('omnipay.akahu'));
    }

    public function testGatewayPassesConfigToRequests(): void
    {
        $request = $this->app->make(Gateway::class)->purchase([
            'amount' => '10.00',
            'returnUrl' => 'https://example.com/return',
        ]);

        $this->assertSame('app_token_test', $request->getAppToken());
        $this->assertSame([
            'amount' => 10.0,
            'redirect_uri' => 'https://example.com/return',
            'payee' => ['name' => 'Acme Ltd', 'account_number' => '12-3456-7890123-00'],
        ], $request->getData());
    }

    public function testGatewayUsesTheCurrentRequest(): void
    {
        $this->app->instance('request', Request::create('/webhooks/akahu', 'POST', [], [], [], [
            'HTTP_X_AKAHU_PAYMENTS_SIGNING_KEY' => 'key_1',
        ], '{"status":"SENT"}'));
        $this->app->forgetScopedInstances();

        $notification = $this->app->make(Gateway::class)->acceptNotification();

        $this->assertSame('{"status":"SENT"}', $notification->getBody());
        $this->assertSame('key_1', $notification->getSigningKey());
    }

    public function testWebhookKeyCacheCanBeDisabled(): void
    {
        $this->app['config']->set('akahu.webhook_key_cache', false);
        $this->app->forgetScopedInstances();

        $this->assertNull($this->app->make(Gateway::class)->getWebhookKeyCache());
    }

    public function testConfigIsPublishable(): void
    {
        $sources = array_keys(AkahuServiceProvider::pathsToPublish(AkahuServiceProvider::class, 'akahu-config'));

        $this->assertContains(realpath(__DIR__ . '/../../config/akahu.php'), array_map('realpath', $sources));
    }
}
