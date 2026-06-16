<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service;

use Kommandhub\PaystackSW\Contracts\HttpClientInterface;
use Kommandhub\PaystackSW\Service\Paystack;
use Kommandhub\PaystackSW\Service\Resources\ApiResource;
use Kommandhub\PaystackSW\Service\Resources\Customer;
use Kommandhub\PaystackSW\Service\Resources\Miscellaneous;
use Kommandhub\PaystackSW\Service\Resources\Plan;
use Kommandhub\PaystackSW\Service\Resources\Refund;
use Kommandhub\PaystackSW\Service\Resources\Settlement;
use Kommandhub\PaystackSW\Service\Resources\Split;
use Kommandhub\PaystackSW\Service\Resources\Subaccount;
use Kommandhub\PaystackSW\Service\Resources\Subscription;
use Kommandhub\PaystackSW\Service\Resources\Transaction;
use Kommandhub\PaystackSW\Service\Resources\Transfer;
use Kommandhub\PaystackSW\Service\Resources\Verification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Paystack::class)]
#[UsesClass(ApiResource::class)]
class PaystackTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Paystack $paystack;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->paystack = new Paystack($this->httpClient);
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
