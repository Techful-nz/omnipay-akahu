<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

class FetchConnectionsResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return !$this->isError() && ($this->data['success'] ?? false) === true;
    }

    /**
     * Each connection has `_id`, `bank` (the payerBank value), `name`, `logo` and `limits`.
     *
     * @return list<array<string, mixed>>
     */
    public function getConnections(): array
    {
        return $this->data['items'] ?? [];
    }
}
