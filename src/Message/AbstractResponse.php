<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

use Omnipay\Common\Message\AbstractResponse as BaseAbstractResponse;
use Omnipay\Common\Message\RequestInterface;

/**
 * @property array<string, mixed> $data
 */
abstract class AbstractResponse extends BaseAbstractResponse
{
    public function __construct(RequestInterface $request, array $data, protected int $statusCode = 200)
    {
        parent::__construct($request, $data);
    }

    public function getHttpStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isError(): bool
    {
        return $this->statusCode >= 400 || ($this->data['success'] ?? true) === false;
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
