<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Client;

use Kommandhub\PaystackSW\Client\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Client\PaystackClient;
use Kommandhub\PaystackSW\Client\Resource\ApiResource;
use Kommandhub\PaystackSW\Client\Resource\Customer;
use Kommandhub\PaystackSW\Client\Resource\Miscellaneous;
use Kommandhub\PaystackSW\Client\Resource\Plan;
use Kommandhub\PaystackSW\Client\Resource\Refund;
use Kommandhub\PaystackSW\Client\Resource\Settlement;
use Kommandhub\PaystackSW\Client\Resource\Split;
use Kommandhub\PaystackSW\Client\Resource\Subaccount;
use Kommandhub\PaystackSW\Client\Resource\Subscription;
use Kommandhub\PaystackSW\Client\Resource\Transaction;
use Kommandhub\PaystackSW\Client\Resource\Transfer;
use Kommandhub\PaystackSW\Client\Resource\Verification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaystackClient::class)]
#[UsesClass(ApiResource::class)]
class PaystackClientTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private PaystackClient $paystack;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->paystack = new PaystackClient($this->httpClient);
    }

    public function testTransactions(): void
    {
        $this->assertInstanceOf(Transaction::class, $this->paystack->transactions());
    }

    public function testCustomers(): void
    {
        $this->assertInstanceOf(Customer::class, $this->paystack->customers());
    }

    public function testPlans(): void
    {
        $this->assertInstanceOf(Plan::class, $this->paystack->plans());
    }

    public function testSplits(): void
    {
        $this->assertInstanceOf(Split::class, $this->paystack->splits());
    }

    public function testSubaccounts(): void
    {
        $this->assertInstanceOf(Subaccount::class, $this->paystack->subaccounts());
    }

    public function testSubscriptions(): void
    {
        $this->assertInstanceOf(Subscription::class, $this->paystack->subscriptions());
    }

    public function testRefunds(): void
    {
        $this->assertInstanceOf(Refund::class, $this->paystack->refunds());
    }

    public function testMiscellaneous(): void
    {
        $this->assertInstanceOf(Miscellaneous::class, $this->paystack->miscellaneous());
    }

    public function testTransfers(): void
    {
        $this->assertInstanceOf(Transfer::class, $this->paystack->transfers());
    }

    public function testSettlements(): void
    {
        $this->assertInstanceOf(Settlement::class, $this->paystack->settlements());
    }

    public function testVerification(): void
    {
        $this->assertInstanceOf(Verification::class, $this->paystack->verification());
    }
}
