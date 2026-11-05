<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

/**
 * A one-off payment as returned by GET /v1/one-off-payments/{id}.
 *
 * isSuccessful() is true only once the payment is SENT. A payment that is
 * READY, AUTHORISED or SUBMITTED is pending.
 */
class PaymentResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return !$this->isError() && $this->getStatus() === PaymentStatus::SENT;
    }

    public function isPending(): bool
    {
        return !$this->isError() && in_array($this->getStatus(), PaymentStatus::PENDING, true);
    }

    public function isCancelled(): bool
    {
        return !$this->isError() && $this->getStatus() === PaymentStatus::CANCELLED;
    }

    public function isFailed(): bool
    {
        return !$this->isError() && $this->getStatus() === PaymentStatus::FAILED;
    }

    /** Whether the payment has reached a final state (SENT, CANCELLED or FAILED). */
    public function isTerminal(): bool
    {
        return in_array($this->getStatus(), PaymentStatus::TERMINAL, true);
    }

    public function getTransactionReference(): ?string
    {
        return $this->data['_id'] ?? null;
    }

    /** READY, AUTHORISED, SUBMITTED, SENT, CANCELLED or FAILED. */
    public function getStatus(): ?string
    {
        return $this->data['status'] ?? null;
    }

    /** status_reason.code, e.g. PENDING, USER_CANCELLED or REJECTED; or the error code for a failed request. */
    public function getCode(): ?string
    {
        return $this->data['status_reason']['code'] ?? parent::getCode();
    }

    public function getAmount(): ?string
    {
        return isset($this->data['amount']) ? number_format((float) $this->data['amount'], 2, '.', '') : null;
    }

    /** @return array<string, mixed>|null */
    public function getPayee(): ?array
    {
        return $this->data['payee'] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function getPayer(): ?array
    {
        return $this->data['payer'] ?? null;
    }

    public function getPayerName(): ?string
    {
        return $this->data['payer']['name'] ?? null;
    }

    public function getPayerAccountNumber(): ?string
    {
        return $this->data['payer']['account_number'] ?? null;
    }

    public function getCreatedAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->data['updated_at'] ?? null;
    }

    public function getSubmittedAt(): ?string
    {
        return $this->data['submitted_at'] ?? null;
    }

    public function getTerminalAt(): ?string
    {
        return $this->data['terminal_at'] ?? null;
    }

    public function getExpiresAt(): ?string
    {
        return $this->data['expires_at'] ?? null;
    }
}
