<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Message;

use DateTimeInterface;
use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Create a one-off payment: POST /v1/one-off-payments.
 *
 * Standard Omnipay parameters used: amount, currency (NZD only), returnUrl
 * (redirect_uri), notifyUrl (webhook_uri).
 */
class PurchaseRequest extends AbstractRequest
{
    private const ACCOUNT_PATTERN = '/^\d{2}-\d{4}-\d{7}-\d{2,3}$/';
    private const NAME_PATTERN = "/^[A-Za-z0-9 '.,&\\-_]{2,70}$/";
    private const REFERENCE_PATTERN = '/^[A-Za-z0-9 \-_]{0,12}$/';
    public const BANKS = ['anz', 'asb', 'bnz', 'kiwibank', 'nzhl', 'westpac', 'demo_bank'];

    // Payee (the account receiving funds)

    public function getPayeeParticulars(): ?string
    {
        return $this->getParameter('payeeParticulars');
    }

    public function setPayeeParticulars(?string $value): static
    {
        return $this->setParameter('payeeParticulars', $value);
    }

    public function getPayeeCode(): ?string
    {
        return $this->getParameter('payeeCode');
    }

    public function setPayeeCode(?string $value): static
    {
        return $this->setParameter('payeeCode', $value);
    }

    public function getPayeeReference(): ?string
    {
        return $this->getParameter('payeeReference');
    }

    public function setPayeeReference(?string $value): static
    {
        return $this->setParameter('payeeReference', $value);
    }

    // Payer (the customer)

    public function getPayerBank(): ?string
    {
        return $this->getParameter('payerBank');
    }

    /** Restrict the payment to one bank, e.g. "anz" or "demo_bank". */
    public function setPayerBank(?string $value): static
    {
        return $this->setParameter('payerBank', $value);
    }

    public function getPayerName(): ?string
    {
        return $this->getParameter('payerName');
    }

    public function setPayerName(?string $value): static
    {
        return $this->setParameter('payerName', $value);
    }

    public function getPayerAccountNumber(): ?string
    {
        return $this->getParameter('payerAccountNumber');
    }

    /** Require the payment to come from this account. */
    public function setPayerAccountNumber(?string $value): static
    {
        return $this->setParameter('payerAccountNumber', $value);
    }

    public function getReleaseAccountDetails(): ?bool
    {
        return $this->getParameter('releaseAccountDetails');
    }

    /** Ask Akahu to return the payer's name and account number once known. */
    public function setReleaseAccountDetails(?bool $value): static
    {
        return $this->setParameter('releaseAccountDetails', $value);
    }

    public function getPayerParticulars(): ?string
    {
        return $this->getParameter('payerParticulars');
    }

    public function setPayerParticulars(?string $value): static
    {
        return $this->setParameter('payerParticulars', $value);
    }

    public function getPayerCode(): ?string
    {
        return $this->getParameter('payerCode');
    }

    public function setPayerCode(?string $value): static
    {
        return $this->setParameter('payerCode', $value);
    }

    public function getPayerReference(): ?string
    {
        return $this->getParameter('payerReference');
    }

    public function setPayerReference(?string $value): static
    {
        return $this->setParameter('payerReference', $value);
    }

    // Payment options

    public function getExpiresAt(): ?string
    {
        return $this->getParameter('expiresAt');
    }

    /** When the payment is cancelled if not authorised (15 minutes to 1 year ahead; default 1 hour). */
    public function setExpiresAt(DateTimeInterface|string|null $value): static
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTimeInterface::RFC3339_EXTENDED);
        }

        return $this->setParameter('expiresAt', $value);
    }

    public function getRedirectMode(): ?string
    {
        return $this->getParameter('redirectMode');
    }

    /** Set to "deep_link" when returnUrl is a universal/app link. */
    public function setRedirectMode(?string $value): static
    {
        return $this->setParameter('redirectMode', $value);
    }

    public function getData(): array
    {
        $this->validate('amount', 'returnUrl', 'payeeName', 'payeeAccountNumber');

        $currency = $this->getCurrency();
        if ($currency !== null && $currency !== 'NZD') {
            throw new InvalidRequestException('Akahu one-off payments only support NZD.');
        }

        $amount = (float) $this->getAmount();
        if ($amount < 0.01 || $amount > 1000000) {
            throw new InvalidRequestException('The amount must be between 0.01 and 1,000,000.');
        }

        $this->assertMatches(self::NAME_PATTERN, $this->getPayeeName(), 'payeeName');
        $this->assertMatches(self::ACCOUNT_PATTERN, $this->getPayeeAccountNumber(), 'payeeAccountNumber');

        $data = [
            'amount' => $amount,
            'redirect_uri' => $this->getReturnUrl(),
            'payee' => $this->filter([
                'name' => $this->getPayeeName(),
                'account_number' => $this->getPayeeAccountNumber(),
                'particulars' => $this->reference($this->getPayeeParticulars(), 'payeeParticulars'),
                'code' => $this->reference($this->getPayeeCode(), 'payeeCode'),
                'reference' => $this->reference($this->getPayeeReference(), 'payeeReference'),
            ]),
        ];

        if ($this->getNotifyUrl()) {
            $data['webhook_uri'] = $this->getNotifyUrl();
        }

        if ($this->getRedirectMode()) {
            $data['redirect_mode'] = $this->getRedirectMode();
        }

        if ($this->getExpiresAt()) {
            $data['expires_at'] = $this->getExpiresAt();
        }

        $payer = $this->buildPayer();
        if ($payer !== []) {
            $data['payer'] = $payer;
        }

        return $data;
    }

    public function sendData($data): PurchaseResponse
    {
        $result = $this->sendApiRequest('POST', '/one-off-payments', $data);

        return $this->response = new PurchaseResponse($this, $result['body'], $result['status']);
    }

    private function buildPayer(): array
    {
        $bank = $this->getPayerBank();
        if ($bank !== null && $bank !== '' && !in_array($bank, self::BANKS, true)) {
            throw new InvalidRequestException(
                sprintf('The payerBank must be one of: %s.', implode(', ', self::BANKS))
            );
        }

        if ($this->getPayerAccountNumber()) {
            $this->assertMatches(self::ACCOUNT_PATTERN, $this->getPayerAccountNumber(), 'payerAccountNumber');

            if ($this->getReleaseAccountDetails()) {
                throw new InvalidRequestException('releaseAccountDetails cannot be used with payerAccountNumber.');
            }
        }

        if ($this->getPayerName()) {
            $this->assertMatches(self::NAME_PATTERN, $this->getPayerName(), 'payerName');
        }

        return $this->filter([
            'bank' => $bank ?: null,
            // Akahu only applies the payer name together with an account number.
            'name' => $this->getPayerAccountNumber() ? $this->getPayerName() : null,
            'account_number' => $this->getPayerAccountNumber(),
            'release_account_details' => $this->getReleaseAccountDetails(),
            'particulars' => $this->reference($this->getPayerParticulars(), 'payerParticulars'),
            'code' => $this->reference($this->getPayerCode(), 'payerCode'),
            'reference' => $this->reference($this->getPayerReference(), 'payerReference'),
        ]);
    }

    private function reference(?string $value, string $name): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $this->assertMatches(
            self::REFERENCE_PATTERN,
            $value,
            $name,
            'up to 12 letters, digits, spaces, "-" or "_"',
        );

        return $value;
    }

    private function assertMatches(string $pattern, ?string $value, string $name, ?string $hint = null): void
    {
        if ($value === null || !preg_match($pattern, $value)) {
            throw new InvalidRequestException(
                sprintf('The %s parameter is invalid%s.', $name, $hint ? " ($hint)" : '')
            );
        }
    }

    private function filter(array $values): array
    {
        return array_filter($values, static fn ($v) => $v !== null && $v !== '');
    }
}
