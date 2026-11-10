<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Exception;

/**
 * The amount is over a limit: single_limit_exceeded or bank_limit_exceeded.
 */
class PaymentLimitExceededException extends ApiException
{
}
