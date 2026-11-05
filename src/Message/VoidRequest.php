<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

/**
 * Cancel a one-off payment: PUT /v1/one-off-payments/{transactionReference}/cancel.
 *
 * Only payments with status READY can be cancelled.
 */
class VoidRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('transactionReference');

        return ['id' => $this->getTransactionReference()];
    }

    public function sendData($data): VoidResponse
    {
        $result = $this->sendApiRequest('PUT', '/one-off-payments/' . rawurlencode($data['id']) . '/cancel');

        return $this->response = new VoidResponse($this, $result['body'], $result['status']);
    }
}
