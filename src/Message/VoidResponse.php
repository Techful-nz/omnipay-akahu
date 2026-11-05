<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

class VoidResponse extends AbstractResponse
{
    /** Akahu returns 204 No Content on success. */
    public function isSuccessful(): bool
    {
        return !$this->isError() && $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function getTransactionReference(): ?string
    {
        return $this->request->getTransactionReference();
    }
}
