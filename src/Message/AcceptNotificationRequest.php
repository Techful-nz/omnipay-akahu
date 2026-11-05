<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Verify and parse a one-off payment webhook.
 *
 * Pass the webhook's raw body and its two headers:
 *
 *     $gateway->acceptNotification([
 *         'body' => $request->getContent(),
 *         'signature' => $request->headers->get('X-Akahu-Payments-Signature'),
 *         'signingKey' => $request->headers->get('X-Akahu-Payments-Signing-Key'),
 *     ])->send();
 *
 * Any of these that are left out are read from the gateway's HTTP request,
 * which is Omnipay's usual behaviour.
 *
 * Akahu signs the raw body with RSA-SHA256. Public keys are fetched from
 * GET /v1/webhooks/keys/{signingKey} unless supplied via webhookPublicKeys.
 *
 * send() throws InvalidRequestException if the signature is missing or invalid.
 */
class AcceptNotificationRequest extends AbstractRequest
{
    public const SIGNATURE_HEADER = 'X-Akahu-Payments-Signature';
    public const KEY_ID_HEADER = 'X-Akahu-Payments-Signing-Key';

    /** @var array<string, string> Public keys fetched during this process, by key ID. */
    private static array $keyCache = [];

    /** The raw webhook body, exactly as received (before JSON decoding). */
    public function setBody(?string $value): static
    {
        return $this->setParameter('body', $value);
    }

    public function getBody(): ?string
    {
        return $this->getParameter('body') ?? ($this->httpRequest->getContent() ?: null);
    }

    /** The X-Akahu-Payments-Signature header value. */
    public function setSignature(?string $value): static
    {
        return $this->setParameter('signature', $value);
    }

    public function getSignature(): ?string
    {
        return $this->getParameter('signature') ?? $this->httpRequest->headers->get(self::SIGNATURE_HEADER);
    }

    /** The X-Akahu-Payments-Signing-Key header value (the key ID). */
    public function setSigningKey(?string $value): static
    {
        return $this->setParameter('signingKey', $value);
    }

    public function getSigningKey(): ?string
    {
        return $this->getParameter('signingKey') ?? $this->httpRequest->headers->get(self::KEY_ID_HEADER);
    }

    /** @param array<string, string> $value [keyId => PEM] */
    public function setWebhookPublicKeys(?array $value): static
    {
        return $this->setParameter('webhookPublicKeys', $value);
    }

    /** @return array<string, string>|null */
    public function getWebhookPublicKeys(): ?array
    {
        return $this->getParameter('webhookPublicKeys');
    }

    public function getData(): array
    {
        $body = $this->getBody();
        $signature = $this->getSignature();
        $keyId = $this->getSigningKey();

        if (!$body || !$signature || !$keyId) {
            throw new InvalidRequestException('The webhook is missing its body, signature or signing key header.');
        }

        $decodedSignature = base64_decode($signature, true);
        $publicKey = openssl_pkey_get_public($this->getPublicKey($keyId));

        if ($decodedSignature === false || $publicKey === false
            || openssl_verify($body, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256) !== 1
        ) {
            throw new InvalidRequestException('The webhook signature is invalid.');
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new InvalidRequestException('The webhook body is not valid JSON.');
        }

        return $data;
    }

    public function sendData($data): Notification
    {
        return $this->response = new Notification($this, $data);
    }

    private function getPublicKey(string $keyId): string
    {
        $known = $this->getWebhookPublicKeys() ?? [];

        if (isset($known[$keyId])) {
            return $known[$keyId];
        }

        $cacheKey = $this->getEndpoint() . '|' . $keyId;

        if (!isset(self::$keyCache[$cacheKey])) {
            $result = $this->sendApiRequest('GET', '/webhooks/keys/' . rawurlencode($keyId));

            if ($result['status'] !== 200 || empty($result['body']['publicKey'])) {
                throw new InvalidRequestException(sprintf('Could not fetch webhook signing key "%s".', $keyId));
            }

            self::$keyCache[$cacheKey] = $result['body']['publicKey'];
        }

        return self::$keyCache[$cacheKey];
    }
}
