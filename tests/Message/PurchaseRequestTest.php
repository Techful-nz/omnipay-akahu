<?php

declare(strict_types=1);

namespace Omnipay\Akahu\Tests\Message;

use DateTimeImmutable;
use DateTimeZone;
use Omnipay\Akahu\Exception\AkahuException;
use Omnipay\Akahu\Exception\InvalidParameterException;
use Omnipay\Akahu\Message\PurchaseRequest;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PurchaseRequestTest extends TestCase
{
    private PurchaseRequest $request;

    protected function setUp(): void
    {
        $this->request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize([
            'appToken' => 'app_token_test',
            'appSecret' => 'secret_test',
            'amount' => '12.34',
            'returnUrl' => 'https://example.com/return',
            'payeeName' => 'Acme Ltd',
            'payeeAccountNumber' => '12-3456-7890123-001',
        ]);
    }

    public function testMinimalData(): void
    {
        $this->assertSame([
            'amount' => 12.34,
            'redirect_uri' => 'https://example.com/return',
            'payee' => ['name' => 'Acme Ltd', 'account_number' => '12-3456-7890123-001'],
        ], $this->request->getData());
    }

    public function testFullData(): void
    {
        $this->request
            ->setNotifyUrl('https://example.com/webhook')
            ->setRedirectMode('deep_link')
            ->setExpiresAt(new DateTimeImmutable('2026-11-06 10:00:00', new DateTimeZone('UTC')))
            ->setPayeeParticulars('Order')
            ->setPayeeCode('42')
            ->setPayeeReference('INV_1234')
            ->setPayerBank('demo_bank')
            ->setPayerName('Frodo Baggins')
            ->setPayerAccountNumber('01-2345-6789012-00')
            ->setPayerReference('Acme');

        $this->assertSame([
            'amount' => 12.34,
            'redirect_uri' => 'https://example.com/return',
            'payee' => [
                'name' => 'Acme Ltd',
                'account_number' => '12-3456-7890123-001',
                'particulars' => 'Order',
                'code' => '42',
                'reference' => 'INV_1234',
            ],
            'webhook_uri' => 'https://example.com/webhook',
            'redirect_mode' => 'deep_link',
            'expires_at' => '2026-11-06T10:00:00.000+00:00',
            'payer' => [
                'bank' => 'demo_bank',
                'name' => 'Frodo Baggins',
                'account_number' => '01-2345-6789012-00',
                'reference' => 'Acme',
            ],
        ], $this->request->getData());
    }

    public function testPayerNameIsDroppedWithoutAccountNumber(): void
    {
        $this->request->setPayerName('Frodo Baggins');

        $this->assertArrayNotHasKey('payer', $this->request->getData());
    }

    public static function invalidParameters(): array
    {
        return [
            'non-NZD currency' => ['setCurrency', 'AUD', 'currency'],
            'bad payee account' => ['setPayeeAccountNumber', '123456789', 'payeeAccountNumber'],
            'payee name too short' => ['setPayeeName', 'A', 'payeeName'],
            'payee name bad chars' => ['setPayeeName', 'Acme <Ltd>', 'payeeName'],
            'reference too long' => ['setPayeeReference', 'ABCDEFGHIJKLM', 'payeeReference'],
            'reference bad chars' => ['setPayeeCode', 'INV#1', 'payeeCode'],
            'unknown bank' => ['setPayerBank', 'hsbc', 'payerBank'],
            'bad payer account' => ['setPayerAccountNumber', '01-2345', 'payerAccountNumber'],
            'amount too large' => ['setAmount', '1000000.01', 'amount'],
            'empty payee name' => ['setPayeeName', '', 'payeeName'],
        ];
    }

    #[DataProvider('invalidParameters')]
    public function testInvalidParameters(string $setter, string $value, string $parameter): void
    {
        $this->request->$setter($value);

        try {
            $this->request->getData();
            $this->fail('Expected an InvalidParameterException.');
        } catch (InvalidParameterException $e) {
            $this->assertSame($parameter, $e->getParameter());
            $this->assertInstanceOf(AkahuException::class, $e);
            // Still an Omnipay InvalidRequestException, for existing catch blocks.
            $this->assertInstanceOf(InvalidRequestException::class, $e);
        }
    }

    public function testReleaseAccountDetailsConflictsWithPayerAccount(): void
    {
        $this->request
            ->setPayerAccountNumber('01-2345-6789012-00')
            ->setReleaseAccountDetails(true);

        $this->expectException(InvalidRequestException::class);
        $this->request->getData();
    }

    public function testRequiresReturnUrl(): void
    {
        $this->request->setReturnUrl(null);

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('The returnUrl parameter is required.');
        $this->request->getData();
    }
}
