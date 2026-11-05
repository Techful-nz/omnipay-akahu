<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

/**
 * Fetch a one-off payment: GET /v1/one-off-payments/{transactionReference}.
 */
class FetchTransactionRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('transactionReference');

        return ['id' => $this->getTransactionReference()];
    }

    public function sendData($data): PaymentResponse
    {
        $result = $this->sendApiRequest('GET', '/one-off-payments/' . rawurlencode($data['id']));

        return $this->response = new PaymentResponse($this, $result['body'], $result['status']);
    }
}
