<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

/**
 * Called when the user returns to your redirect URI.
 *
 * Akahu adds no query parameters to the redirect URI, so pass the
 * transactionReference you stored after purchase(). The payment may still be
 * pending (SUBMITTED) at this point: check isPending() and rely on webhooks
 * or polling for the final result.
 */
class CompletePurchaseRequest extends FetchTransactionRequest
{
}
