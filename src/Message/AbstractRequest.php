<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

use Omnipay\Akahu\Exception\InvalidParameterException;
use Http\Discovery\Psr17FactoryDiscovery;
use Omnipay\Common\Message\AbstractRequest as BaseAbstractRequest;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Psr\Http\Message\ResponseInterface as HttpResponse;

abstract class AbstractRequest extends BaseAbstractRequest
{
    public const ENDPOINT = 'https://api.payments.akahu.io/v1';

    /** @var array{string, string}|null Method and URL of the last API call. */
    private ?array $lastApiCall = null;

    public function getAppToken(): ?string
    {
        return $this->getParameter('appToken');
    }

    public function setAppToken(?string $value): static
    {
        return $this->setParameter('appToken', $value);
    }

    public function getAppSecret(): ?string
    {
        return $this->getParameter('appSecret');
    }

    public function setAppSecret(?string $value): static
    {
        return $this->setParameter('appSecret', $value);
    }

    // Payee (the account receiving funds). Defaults can be set on the gateway.

    public function getPayeeName(): ?string
    {
        return $this->getParameter('payeeName');
    }

    public function setPayeeName(?string $value): static
    {
        return $this->setParameter('payeeName', $value);
    }

    public function getPayeeAccountNumber(): ?string
    {
        return $this->getParameter('payeeAccountNumber');
    }

    public function setPayeeAccountNumber(?string $value): static
    {
        return $this->setParameter('payeeAccountNumber', $value);
    }

    /**
     * Omnipay's validate(), but an empty string also counts as missing, and
     * the exception says which parameter it was.
     *
     * @throws InvalidParameterException
     */
    public function validate(...$args): void
    {
        foreach ($args as $key) {
            $value = $this->getParameter($key);

            if ($value === null || $value === '') {
                throw InvalidParameterException::required($key);
            }
        }
    }

    public function getEndpoint(): string
    {
        return $this->getParameter('endpoint') ?? self::ENDPOINT;
    }

    /** Override the API base URL (no trailing slash). */
    public function setEndpoint(?string $value): static
    {
        return $this->setParameter('endpoint', $value);
    }

    /**
     * Send an authenticated request to the Akahu one-off payments API.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function sendApiRequest(string $method, string $path, ?array $body = null): array
    {
        $this->validate('appToken', 'appSecret');

        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->getAppToken() . ':' . $this->getAppSecret()),
            'Accept' => 'application/json',
        ];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $url = rtrim($this->getEndpoint(), '/') . $path;
        $this->lastApiCall = [$method, $url];

        $httpResponse = $this->httpClient->request(
            $method,
            $url,
            $headers,
            $body !== null ? json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : null,
        );

        return [
            'status' => $httpResponse->getStatusCode(),
            'body' => $this->decode($httpResponse),
        ];
    }

    /**
     * The last API call as a PSR-7 request, for exceptions. It has the method
     * and URL only, so credentials never end up in logs or error reports.
     */
    public function getLastApiRequest(): ?PsrRequest
    {
        if ($this->lastApiCall === null) {
            return null;
        }

        return Psr17FactoryDiscovery::findRequestFactory()->createRequest(...$this->lastApiCall);
    }

    private function decode(HttpResponse $response): array
    {
        $contents = (string) $response->getBody();

        if ($contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : ['message' => $contents];
    }
}
