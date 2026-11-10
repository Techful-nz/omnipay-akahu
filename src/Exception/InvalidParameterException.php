<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Exception;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * A request parameter is missing or invalid. Thrown before anything is sent
 * to Akahu.
 *
 * Extends Omnipay's InvalidRequestException so existing catch blocks still work.
 */
class InvalidParameterException extends InvalidRequestException implements AkahuException
{
    final public function __construct(string $message, private readonly string $parameter)
    {
        parent::__construct($message);
    }

    public static function required(string $parameter): static
    {
        return new static(sprintf('The %s parameter is required.', $parameter), $parameter);
    }

    public static function invalid(string $parameter, string $reason): static
    {
        return new static(sprintf('The %s parameter is invalid: %s.', $parameter, $reason), $parameter);
    }

    /** The parameter's name, e.g. "payeeAccountNumber". */
    public function getParameter(): string
    {
        return $this->parameter;
    }
}
