<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Exception;

/**
 * bad_payment_state: the payment can no longer be changed, e.g. cancelling one that is not READY.
 */
class InvalidPaymentStateException extends ApiException
{
}
