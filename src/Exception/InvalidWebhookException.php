<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Exception;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * A webhook could not be trusted: missing headers, a bad signature, an
 * unknown signing key, or a body that is not JSON. Respond with HTTP 400.
 *
 * Extends Omnipay's InvalidRequestException so existing catch blocks still work.
 */
class InvalidWebhookException extends InvalidRequestException implements AkahuException
{
}
