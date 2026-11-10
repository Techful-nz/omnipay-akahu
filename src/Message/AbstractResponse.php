<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

use Omnipay\Akahu\Exception\ApiException;
use Omnipay\Common\Http\Exception\RequestException;
use Omnipay\Common\Message\AbstractResponse as BaseAbstractResponse;
use Omnipay\Common\Message\RequestInterface;

/**
 * @property array<string, mixed> $data
 */
abstract class AbstractResponse extends BaseAbstractResponse
{
    /** Built with the response, so its stack trace points at send(). */
    private ?RequestException $exception;

    public function __construct(RequestInterface $request, array $data, protected int $statusCode = 200)
    {
        parent::__construct($request, $data);

        $this->exception = $this->isError() && $request instanceof AbstractRequest
            ? ApiException::fromResponse($request->getLastApiRequest(), $statusCode, $data)
            : null;
    }

    public function getHttpStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isError(): bool
    {
        return $this->statusCode >= 400 || ($this->data['success'] ?? true) === false;
    }

    /**
     * The exception for this error response, or null if the request
     * succeeded: an ApiException subclass for a known Akahu error code,
     * otherwise Omnipay's RequestException. A payment that is FAILED or
     * CANCELLED is not an error.
     */
    public function getException(): ?RequestException
    {
        return $this->exception;
    }

    /**
     * Throw if Akahu returned an error; otherwise return the response.
     *
     * @throws RequestException
     */
    public function throw(): static
    {
        $exception = $this->getException();

        if ($exception !== null) {
            throw $exception;
        }

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->data['message'] ?? $this->data['status_reason']['message'] ?? null;
    }

    /**
     * Validation issues returned with an error, each with `code`, `message` and `path`.
     *
     * @return list<array{code?: string, message?: string, path?: array}>
     */
    public function getIssues(): array
    {
        return $this->data['issues'] ?? [];
    }

    public function getCode(): ?string
    {
        return $this->data['issues'][0]['code'] ?? null;
    }
}
