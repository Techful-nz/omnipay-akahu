# Omnipay: Akahu (Pay by Bank)

**[Akahu](https://akahu.nz) one-off payments driver for the [Omnipay](https://github.com/thephpleague/omnipay) PHP payment processing library.**

Lets New Zealand customers pay straight from their bank account via open banking ([Akahu one-off payments](https://developers.akahu.nz/docs/oneoff-payments)). Supports ANZ, ASB, BNZ, Kiwibank, NZHL, Westpac and Akahu's Demo Bank.

## Installation

```bash
composer require techful-nz/omnipay-akahu
```

Requires PHP 8.1+ and `omnipay/common` v3. You also need a PSR-18 HTTP client, e.g. `php-http/guzzle7-adapter`.

## Setup

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('Akahu');
$gateway->initialize([
    'appToken' => getenv('AKAHU_APP_TOKEN'),   // app_token_...
    'appSecret' => getenv('AKAHU_APP_SECRET'),
    // Default payee: the account that receives the funds.
    'payeeName' => 'Acme Ltd',
    'payeeAccountNumber' => '12-3456-7890123-00',
]);
```

Requests use HTTP Basic auth (app token : app secret) against `https://api.payments.akahu.io/v1`.

## Supported methods

| Omnipay method | Akahu endpoint |
|---|---|
| `purchase()` | `POST /v1/one-off-payments` |
| `completePurchase()` / `fetchTransaction()` | `GET /v1/one-off-payments/{id}` |
| `void()` | `PUT /v1/one-off-payments/{id}/cancel` (only while `READY`) |
| `fetchConnections()` | `GET /v1/connections` |
| `acceptNotification()` | Verifies and parses a webhook |

Akahu has no refund API for one-off payments, so `refund()` is not supported.

## 1. Create the payment and redirect

```php
$response = $gateway->purchase([
    'amount' => '25.00',
    'currency' => 'NZD',                           // optional; only NZD is accepted
    'returnUrl' => 'https://example.com/checkout/return?order=1234',
    'notifyUrl' => 'https://example.com/webhooks/akahu',
    'payeeReference' => 'INV-1234',                // max 12 chars: A-Z a-z 0-9 space - _
    'payeeParticulars' => 'Acme',
    'payeeCode' => 'Order1234',
])->send();

if ($response->isRedirect()) {
    // Store this: Akahu adds no parameters to the return URL.
    $order->akahu_payment_id = $response->getTransactionReference();
    $order->save();

    $response->redirect();   // or redirect to $response->getRedirectUrl()
} else {
    echo $response->getMessage();   // e.g. "Invalid payee"; getCode() gives "invalid_payee"
}
```

### Purchase parameters

| Parameter | Akahu field | Notes |
|---|---|---|
| `amount` | `amount` | 0.01 to 1,000,000 NZD |
| `returnUrl` | `redirect_uri` | Required |
| `notifyUrl` | `webhook_uri` | |
| `payeeName`, `payeeAccountNumber` | `payee.name`, `payee.account_number` | Required (or set on the gateway). Account format `12-3456-7890123-00` |
| `payeeParticulars`, `payeeCode`, `payeeReference` | `payee.*` | Shown on your statement |
| `payerParticulars`, `payerCode`, `payerReference` | `payer.*` | Shown on the customer's statement |
| `payerBank` | `payer.bank` | `anz`, `asb`, `bnz`, `kiwibank`, `nzhl`, `westpac`, `demo_bank` |
| `payerAccountNumber`, `payerName` | `payer.account_number`, `payer.name` | Force the paying account |
| `releaseAccountDetails` | `payer.release_account_details` | Return the payer's name and account once paid |
| `expiresAt` | `expires_at` | `DateTimeInterface` or ISO 8601 string. Default 1 hour |
| `redirectMode` | `redirect_mode` | `deep_link` for app/universal links |

## 2. When the customer returns

```php
$response = $gateway->completePurchase([
    'transactionReference' => $order->akahu_payment_id,
])->send();

if ($response->isSuccessful()) {
    // SENT: funds have settled.
} elseif ($response->isPending()) {
    // READY, AUTHORISED or SUBMITTED. Wait for the webhook, or poll fetchTransaction().
} else {
    // CANCELLED or FAILED
    $response->getStatus();   // "CANCELLED"
    $response->getCode();     // e.g. "USER_CANCELLED", "TIMED_OUT", "REJECTED"
}
```

A payment is often still `SUBMITTED` when the customer returns, so don't treat pending as failed. `PaymentResponse` also has `isCancelled()`, `isFailed()`, `isTerminal()`, `getAmount()`, `getPayerName()`, `getPayerAccountNumber()` and the timestamp getters.

## 3. Webhooks

Akahu POSTs to `notifyUrl` on every status change and signs the raw body with RSA-SHA256. `acceptNotification()` reads the request's `X-Akahu-Payments-Signature` and `X-Akahu-Payments-Signing-Key` headers, fetches the public key from Akahu (cached per process), and verifies the signature. It throws `InvalidRequestException` if verification fails.

```php
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\NotificationInterface;

try {
    $notification = $gateway->acceptNotification()->send();
} catch (InvalidRequestException $e) {
    http_response_code(400);
    exit;
}

$order = Order::findByAkahuId($notification->getTransactionReference());

// Webhooks may arrive out of order.
if ($notification->getEmittedAt() > $order->akahu_updated_at) {
    match ($notification->getTransactionStatus()) {
        NotificationInterface::STATUS_COMPLETED => $order->markPaid(),
        NotificationInterface::STATUS_FAILED => $order->markFailed($notification->getCode()),
        default => null,
    };
}

http_response_code(200);   // respond within 10 seconds
```

To avoid fetching keys over HTTP (e.g. you cache them yourself), pass them in:

```php
$gateway->setWebhookPublicKeys(['key_id' => $pem]);
```

The `status_reason` message is for your logs, not for end users.

## Testing with the Demo Bank

Use `'payerBank' => 'demo_bank'` (or pick Demo Bank in the flow). Any username and password work. The payee must be a real NZ account number. Some amounts trigger set outcomes:

| Amount | Outcome |
|---|---|
| 5.02 | `FAILED` / `INTERNAL_ERROR` |
| 5.03 | `FAILED` / `BANK_ERROR` |
| 5.15 | `FAILED` / `REJECTED` |
| 5.20 | `SENT` after 30 seconds |
| 5.22 | `FAILED` / `REJECTED` after 30 seconds |

See the [Demo Bank docs](https://developers.akahu.nz/docs/demo-bank) for the full list. Akahu does not send one-off webhooks from the Demo Bank, so poll `fetchTransaction()` there.

## Running the tests

```bash
composer install
composer test
```

## License

MIT
