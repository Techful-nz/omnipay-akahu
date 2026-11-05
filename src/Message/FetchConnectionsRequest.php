<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

/**
 * List banks available for one-off payments: GET /v1/connections.
 */
class FetchConnectionsRequest extends AbstractRequest
{
    public function getData(): array
    {
        return [];
    }

    public function sendData($data): FetchConnectionsResponse
    {
        $result = $this->sendApiRequest('GET', '/connections');

        return $this->response = new FetchConnectionsResponse($this, $result['body'], $result['status']);
    }
}
