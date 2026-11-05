<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

/**
 * One-off payment statuses.
 *
 * @see https://developers.akahu.nz/docs/oneoff-payments-lifecycle
 */
final class PaymentStatus
{
    public const READY = 'READY';
    public const AUTHORISED = 'AUTHORISED';
    public const SUBMITTED = 'SUBMITTED';
    public const SENT = 'SENT';
    public const CANCELLED = 'CANCELLED';
    public const FAILED = 'FAILED';

    public const PENDING = [self::READY, self::AUTHORISED, self::SUBMITTED];
    public const TERMINAL = [self::SENT, self::CANCELLED, self::FAILED];

    private function __construct()
    {
    }
}
