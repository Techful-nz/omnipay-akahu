<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

use Omnipay\Common\Message\NotificationInterface;

/**
 * A verified ONE_OFF_PAYMENT_STATUS_UPDATE webhook.
 *
 * Webhooks can arrive out of order: compare getEmittedAt() with the last one
 * you processed for the payment.
 */
class Notification extends AbstractResponse implements NotificationInterface
{
    public const TYPE = 'ONE_OFF_PAYMENT_STATUS_UPDATE';

    public function isSuccessful(): bool
    {
        return $this->getStatus() === PaymentStatus::SENT;
    }

    public function getType(): ?string
    {
        return $this->data['type'] ?? null;
    }

    public function getTransactionReference(): ?string
    {
        return $this->data['_payment'] ?? null;
    }

    public function getStatus(): ?string
    {
        return $this->data['status'] ?? null;
    }

    public function getCode(): ?string
    {
        return $this->data['status_reason']['code'] ?? null;
    }

    public function getMessage(): ?string
    {
        return $this->data['status_reason']['message'] ?? null;
    }

    public function getEmittedAt(): ?string
    {
        return $this->data['emitted_at'] ?? null;
    }

    public function getTransactionStatus(): string
    {
        return match ($this->getStatus()) {
            PaymentStatus::SENT => NotificationInterface::STATUS_COMPLETED,
            PaymentStatus::CANCELLED, PaymentStatus::FAILED => NotificationInterface::STATUS_FAILED,
            default => NotificationInterface::STATUS_PENDING,
        };
    }
}
