<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Verify and parse a one-off payment webhook.
 *
 * Akahu signs the raw body with RSA-SHA256. The signature is in the
 * X-Akahu-Payments-Signature header and the key ID in
 * X-Akahu-Payments-Signing-Key. Public keys are fetched from
 * GET /v1/webhooks/keys/{keyId} unless supplied via webhookPublicKeys.
 *
 * send() throws InvalidRequestException if the signature is missing or invalid.
 */
class AcceptNotificationRequest extends AbstractRequest
{
    public const SIGNATURE_HEADER = 'X-Akahu-Payments-Signature';
    public const KEY_ID_HEADER = 'X-Akahu-Payments-Signing-Key';

    /** @var array<string, string> Public keys fetched during this process, by key ID. */
    private static array $keyCache = [];

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
        $body = $this->httpRequest->getContent();
        $signature = $this->httpRequest->headers->get(self::SIGNATURE_HEADER);
        $keyId = $this->httpRequest->headers->get(self::KEY_ID_HEADER);

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
