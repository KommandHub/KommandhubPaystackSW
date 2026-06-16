<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Processor;

use Kommandhub\PaystackSW\Checkout\Payment\Processor\RefundAggregationResult;
use Kommandhub\PaystackSW\Checkout\Payment\Processor\RefundAggregator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[CoversClass(RefundAggregator::class)]
#[UsesClass(RefundAggregationResult::class)]
class RefundAggregatorTest extends TestCase
{
    private RefundAggregator $aggregator;

    protected function setUp(): void
    {
        $this->aggregator = new RefundAggregator();
    }

    public function testAggregateFullRefund(): void
    {
        $transaction = $this->createOrderTransaction(100.00);
        $capture = $this->createCapture('capture-1', 100.00);
        $refund = $this->createRefund('refund-1', 100.00, OrderTransactionCaptureRefundStates::STATE_COMPLETED);

        $capture->setRefunds(new OrderTransactionCaptureRefundCollection([$refund]));
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        $result = $this->aggregator->aggregate($transaction, 'some-other-id');

        $this->assertTrue($result->isFullyRefunded);
        $this->assertArrayHasKey('capture-1', $result->captures);
        $this->assertTrue($result->captures['capture-1']->isFullyRefunded);
    }

    public function testAggregatePartialRefund(): void
    {
        $transaction = $this->createOrderTransaction(100.00);
        $capture = $this->createCapture('capture-1', 100.00);
        $refund = $this->createRefund('refund-1', 40.00, OrderTransactionCaptureRefundStates::STATE_COMPLETED);

        $capture->setRefunds(new OrderTransactionCaptureRefundCollection([$refund]));
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        $result = $this->aggregator->aggregate($transaction, 'some-other-id');

        $this->assertFalse($result->isFullyRefunded);
        $this->assertFalse($result->captures['capture-1']->isFullyRefunded);
    }

    public function testAggregateIncludesCurrentRefund(): void
    {
        $transaction = $this->createOrderTransaction(100.00);
        $capture = $this->createCapture('capture-1', 100.00);
        // This refund is not completed yet
        $refund = $this->createRefund('current-refund-id', 100.00, OrderTransactionCaptureRefundStates::STATE_OPEN);

        $capture->setRefunds(new OrderTransactionCaptureRefundCollection([$refund]));
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        // We pass the 'current-refund-id' so it's included in calculation
        $result = $this->aggregator->aggregate($transaction, 'current-refund-id');

        $this->assertTrue($result->isFullyRefunded);
    }

    private function createOrderTransaction(float $amount): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');
        $transaction->setAmount(
            new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        return $transaction;
    }

    private function createCapture(string $id, float $amount): OrderTransactionCaptureEntity
    {
        $capture = new OrderTransactionCaptureEntity();
        $capture->setId($id);
        $capture->setAmount(
            new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        return $capture;
    }

    private function createRefund(string $id, float $amount, string $state): OrderTransactionCaptureRefundEntity
    {
        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId($id);
        $refund->setAmount(
            new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $stateEntity = new StateMachineStateEntity();
        $stateEntity->setTechnicalName($state);
        $refund->setStateMachineState($stateEntity);

        return $refund;
    }
}
