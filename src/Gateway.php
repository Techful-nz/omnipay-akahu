<?php

declare(strict_types=1);

namespace Omnipay\Akahu;

use Omnipay\Akahu\Message\AcceptNotificationRequest;
use Omnipay\Akahu\Message\CompletePurchaseRequest;
use Omnipay\Akahu\Message\FetchConnectionsRequest;
use Omnipay\Akahu\Message\FetchTransactionRequest;
use Omnipay\Akahu\Message\PurchaseRequest;
use Omnipay\Akahu\Message\VoidRequest;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\RequestInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Akahu pay-by-bank (one-off payments) gateway.
 *
 * @see https://developers.akahu.nz/docs/oneoff-payments
 *
 * @method RequestInterface authorize(array $options = [])
 * @method RequestInterface completeAuthorize(array $options = [])
 * @method RequestInterface capture(array $options = [])
 * @method RequestInterface refund(array $options = [])
 * @method RequestInterface createCard(array $options = [])
 * @method RequestInterface updateCard(array $options = [])
 * @method RequestInterface deleteCard(array $options = [])
 */
class Gateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'Akahu';
    }

    public function getDefaultParameters(): array
    {
        return [
            'appToken' => '',
            'appSecret' => '',
            'payeeName' => '',
            'payeeAccountNumber' => '',
        ];
    }

    public function getAppToken(): ?string
    {
        return $this->getParameter('appToken');
    }

    public function setAppToken(?string $value): static
    {
        return $this->setParameter('appToken', $value);
    }

    public function getAppSecret(): ?string
    {
        return $this->getParameter('appSecret');
    }

    public function setAppSecret(?string $value): static
    {
        return $this->setParameter('appSecret', $value);
    }

    /** Default payee (your business) name, used when a request does not set one. */
    public function getPayeeName(): ?string
    {
        return $this->getParameter('payeeName');
    }

    public function setPayeeName(?string $value): static
    {
        return $this->setParameter('payeeName', $value);
    }

    /** Default payee NZ bank account, format 12-3456-7890123-00. */
    public function getPayeeAccountNumber(): ?string
    {
        return $this->getParameter('payeeAccountNumber');
    }

    public function setPayeeAccountNumber(?string $value): static
    {
        return $this->setParameter('payeeAccountNumber', $value);
    }

    /**
     * Known webhook public keys, as [keyId => PEM]. Keys not listed here are
     * fetched from Akahu on demand.
     *
     * @param array<string, string> $value
     */
    public function setWebhookPublicKeys(array $value): static
    {
        return $this->setParameter('webhookPublicKeys', $value);
    }

    /** @return array<string, string>|null */
    public function getWebhookPublicKeys(): ?array
    {
        return $this->getParameter('webhookPublicKeys');
    }

    /** A PSR-16 cache for fetched webhook public keys, shared across processes. */
    public function setWebhookKeyCache(?CacheInterface $value): static
    {
        return $this->setParameter('webhookKeyCache', $value);
    }

    public function getWebhookKeyCache(): ?CacheInterface
    {
        return $this->getParameter('webhookKeyCache');
    }

    /** How long to keep keys in webhookKeyCache, in seconds (null: the cache's default). */
    public function setWebhookKeyCacheTtl(?int $value): static
    {
        return $this->setParameter('webhookKeyCacheTtl', $value);
    }

    public function getWebhookKeyCacheTtl(): ?int
    {
        return $this->getParameter('webhookKeyCacheTtl');
    }

    /** Create a one-off payment. The response redirects the user to Akahu. */
    public function purchase(array $options = []): PurchaseRequest
    {
        return $this->createRequest(PurchaseRequest::class, $options);
    }

    /** Fetch the payment's current status after the user returns to your redirect URI. */
    public function completePurchase(array $options = []): CompletePurchaseRequest
    {
        return $this->createRequest(CompletePurchaseRequest::class, $options);
    }

    public function fetchTransaction(array $options = []): FetchTransactionRequest
    {
        return $this->createRequest(FetchTransactionRequest::class, $options);
    }

    /** Cancel a payment that is still READY (not yet authorised). */
    public function void(array $options = []): VoidRequest
    {
        return $this->createRequest(VoidRequest::class, $options);
    }

    /** List banks available for one-off payments. */
    public function fetchConnections(array $options = []): FetchConnectionsRequest
    {
        return $this->createRequest(FetchConnectionsRequest::class, $options);
    }

    /** Verify and parse an incoming webhook. */
    public function acceptNotification(array $options = []): AcceptNotificationRequest
    {
        return $this->createRequest(AcceptNotificationRequest::class, $options);
    }
}
