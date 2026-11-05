<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Omnipay\Akahu\Gateway;
use Omnipay\Common\Http\Client;

/**
 * Registers a configured Akahu Gateway in the Laravel container.
 *
 * The gateway is scoped: one instance per request (or Octane request / queued
 * job), built with the current Laravel request.
 */
class AkahuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/akahu.php', 'akahu');

        $this->app->scoped(Gateway::class, static function (Application $app): Gateway {
            $config = $app['config']->get('akahu', []);

            $gateway = new Gateway(new Client(), $app['request']);
            $gateway->initialize(array_filter([
                'appToken' => $config['app_token'] ?? null,
                'appSecret' => $config['app_secret'] ?? null,
                'payeeName' => $config['payee_name'] ?? null,
                'payeeAccountNumber' => $config['payee_account_number'] ?? null,
            ], static fn ($value) => $value !== null && $value !== ''));

            $cacheConfig = $config['webhook_key_cache'] ?? [];
            if ($cacheConfig !== false) {
                $gateway->setWebhookKeyCache($app->make(CacheFactory::class)->store($cacheConfig['store'] ?? null));
                $gateway->setWebhookKeyCacheTtl(isset($cacheConfig['ttl']) ? (int) $cacheConfig['ttl'] : null);
            }

            return $gateway;
        });

        $this->app->alias(Gateway::class, 'omnipay.akahu');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/akahu.php' => $this->app->configPath('akahu.php'),
            ], 'akahu-config');
        }
    }
}
