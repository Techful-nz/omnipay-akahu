<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * Response to creating a one-off payment. On success, redirect the user to
 * the authorisation URL so they can approve the payment with their bank.
 */
class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    /** Never true: the payment only completes once the user authorises it. */
    public function isSuccessful(): bool
    {
        return false;
    }

    public function isRedirect(): bool
    {
        return !$this->isError() && isset($this->data['authorisation_url']);
    }

    public function getRedirectUrl(): ?string
    {
        return $this->data['authorisation_url'] ?? null;
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectData(): array
    {
        return [];
    }

    /** The payment ID, e.g. one_off_payment_xxx. Store it to complete the purchase later. */
    public function getTransactionReference(): ?string
    {
        return $this->data['_id'] ?? null;
    }

    public function getExpiresAt(): ?string
    {
        return $this->data['expires_at'] ?? null;
    }
}
