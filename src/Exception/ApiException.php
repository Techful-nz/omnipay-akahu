<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Exception;

use Omnipay\Common\Http\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Base class for Akahu error codes worth handling on their own.
 *
 * Akahu error bodies look like:
 *     {"success": false, "message": "...", "issues": [{"code": "...", "message": "...", "path": [...]}]}
 *
 * Any other error response becomes Omnipay's own RequestException, the same
 * exception the HTTP client throws, so one catch covers both.
 */
abstract class ApiException extends RequestException implements AkahuException
{
    /**
     * @param array<string, mixed> $body The decoded error body.
     */
    final public function __construct(
        string $message,
        RequestInterface $request,
        protected int $httpStatusCode,
        protected array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $request, $previous);

        $this->code = $httpStatusCode;
    }

    /**
     * Build the exception for an Akahu error response: an ApiException
     * subclass for a known error code, otherwise a RequestException.
     *
     * @param array<string, mixed> $body
     */
    public static function fromResponse(RequestInterface $request, int $httpStatusCode, array $body): RequestException
    {
        $codes = array_column(is_array($body['issues'] ?? null) ? $body['issues'] : [], 'code');

        $class = match (true) {
            in_array('bad_payment_state', $codes, true) => InvalidPaymentStateException::class,
            in_array('single_limit_exceeded', $codes, true),
            in_array('bank_limit_exceeded', $codes, true) => PaymentLimitExceededException::class,
            default => null,
        };

        $message = is_string($body['message'] ?? null) && $body['message'] !== '' ? $body['message'] : null;

        if ($class !== null) {
            return new $class($message ?? 'Akahu rejected the request.', $request, $httpStatusCode, $body);
        }

        return new RequestException(
            sprintf('Akahu API request failed with HTTP %d%s', $httpStatusCode, $message !== null ? ": $message" : '.'),
            $request,
        );
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /** The first issue code, e.g. "single_limit_exceeded". */
    public function getErrorCode(): ?string
    {
        return $this->getIssues()[0]['code'] ?? null;
    }

    /** @return list<string> Every issue code in the response. */
    public function getErrorCodes(): array
    {
        return array_values(array_filter(array_column($this->getIssues(), 'code'), 'is_string'));
    }

    /**
     * @return list<array{code?: string, message?: string, path?: array}>
     */
    public function getIssues(): array
    {
        return is_array($this->body['issues'] ?? null) ? $this->body['issues'] : [];
    }

    /** @return array<string, mixed> The decoded error body. */
    public function getResponseBody(): array
    {
        return $this->body;
    }
}
