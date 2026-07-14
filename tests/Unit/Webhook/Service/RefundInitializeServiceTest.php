<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Webhook\Service;

use Kommandhub\PaystackSW\Webhook\Service\RefundInitializeService;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Kommandhub\PaystackSW\Util\OrderCurrencyResolver;
use Kommandhub\PaystackSW\Util\PaystackCurrencyHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;

#[CoversClass(RefundInitializeService::class)]
#[UsesClass(PaystackCurrencyHelper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(OrderCurrencyResolver::class)]
class RefundInitializeServiceTest extends TestCase
{
    private EntityRepository&MockObject $orderTransactionRepository;
    private EntityRepository&MockObject $orderTransactionCaptureRepository;
    private EntityRepository&MockObject $orderTransactionCaptureRefundRepository;
    private InitialStateIdLoader&MockObject $initialStateIdLoader;
    private ConfigurableLogger&MockObject $logger;
    private RefundInitializeService $service;

    protected function setUp(): void
    {
        $this->orderTransactionRepository = $this->createMock(EntityRepository::class);
        $this->orderTransactionCaptureRepository = $this->createMock(EntityRepository::class);
        $this->orderTransactionCaptureRefundRepository = $this->createMock(EntityRepository::class);
        $this->initialStateIdLoader = $this->createMock(InitialStateIdLoader::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);

        $this->service = new RefundInitializeService(
            $this->orderTransactionRepository,
            $this->orderTransactionCaptureRepository,
            $this->orderTransactionCaptureRefundRepository,
            $this->initialStateIdLoader,
            $this->logger
        );
    }

    public function testHandleInvalidData(): void
    {
        $data = ['id' => 'refund-1']; // Missing transaction_reference
        $context = Context::createDefaultContext();

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Invalid refund data'));

        $this->service->handle($data, $context);
    }

    public function testHandleTransactionNotFound(): void
    {
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 1000,
        ];
        $context = Context::createDefaultContext();

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createEmptySearchResult());

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Could not find transaction'));

        $this->service->handle($data, $context);
    }

    public function testHandleInvalidAmount(): void
    {
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 0,
        ];
        $context = Context::createDefaultContext();

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(100.00)); // refundable base
        $this->attachCurrency($transaction);

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Invalid refund amount'));

        $this->service->handle($data, $context);
    }

    public function testHandleSuccessNewCaptureNewRefund(): void
    {
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 1000,
            'merchant_note' => 'test note',
        ];
        $context = Context::createDefaultContext();

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(100.00)); // refundable base
        $this->attachCurrency($transaction);

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        // getOrCreateCapture -> entityExists check
        $this->orderTransactionCaptureRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        // createNewCapture
        $this->initialStateIdLoader->method('get')->willReturn('state-id');
        $this->orderTransactionCaptureRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $payload) use ($transaction) {
                return $payload[0]['orderTransactionId'] === $transaction->getId();
            }), $context);

        // refundExists
        $this->orderTransactionCaptureRefundRepository->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturnOnConsecutiveCalls(
                $this->createEmptyIdSearchResult(), // for refundExists check
                $this->createEmptyIdSearchResult()  // for entityExists check in createRefund
            );

        // createRefund
        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $payload) {
                return $payload[0]['reason'] === 'test note';
            }), $context);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Successfully initialized refund'));

        $this->service->handle($data, $context);
    }

    public function testHandleRefundAlreadyExists(): void
    {
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 1000,
        ];
        $context = Context::createDefaultContext();

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(100.00)); // refundable base
        $this->attachCurrency($transaction);

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $captureId = Uuid::fromStringToHex('paystack-capture-refund-1-trans-1');
        $this->orderTransactionCaptureRepository->method('searchIds')
            ->willReturn($this->createIdSearchResult([$captureId]));

        $this->orderTransactionCaptureRefundRepository->method('searchIds')
            ->willReturn($this->createIdSearchResult(['refund-exists-id']));

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Refund already exists'));

        $this->service->handle($data, $context);
    }

    public function testHandleExistingCaptureInTransaction(): void
    {
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 1000,
        ];
        $context = Context::createDefaultContext();

        $externalReference = 'refund-1-trans-1';
        $capture = new OrderTransactionCaptureEntity();
        $capture->setId('capture-id-999');
        $capture->setExternalReference($externalReference);
        // Matches the webhook refund amount (1000 minor units = 10.00 NGN).
        $capture->setAmount($this->price(10.00));

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(100.00)); // refundable base
        $this->attachCurrency($transaction);
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        // getOrCreateCapture -> entityExists check
        $this->orderTransactionCaptureRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        // refundExists
        $this->orderTransactionCaptureRefundRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('create');

        $this->service->handle($data, $context);
    }

    public function testHandleCreatesNewCaptureWhenNoTransactionCaptureMatches(): void
    {
        $data = [
            'id' => 'refund-2',
            'transaction_reference' => 'trans-1',
            'amount' => 1000,
        ];
        $context = Context::createDefaultContext();

        // The transaction already has a capture, but for a different refund.
        $otherCapture = new OrderTransactionCaptureEntity();
        $otherCapture->setId('capture-id-other');
        $otherCapture->setExternalReference('refund-1-trans-1');
        $otherCapture->setAmount($this->price(99.00));

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(100.00)); // refundable base
        $this->attachCurrency($transaction);
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$otherCapture]));

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->initialStateIdLoader->method('get')->willReturn('state-id');

        // Falls through to creating a capture for this refund.
        $this->orderTransactionCaptureRepository->expects($this->once())
            ->method('create');

        $this->orderTransactionCaptureRefundRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('create');

        $this->service->handle($data, $context);
    }

    public function testHandleRejectsRefundExceedingTransactionTotal(): void
    {
        // Real payload shape: a 700.00 NGN refund against a 60.00 NGN transaction.
        $data = [
            'id' => '17665402',
            'transaction_reference' => '4dw6pi3639',
            'amount' => 70000,
            'currency' => 'NGN',
        ];
        $context = Context::createDefaultContext();

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(60.00));
        $this->attachCurrency($transaction);
        $transaction->setCaptures(new OrderTransactionCaptureCollection([]));

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->orderTransactionCaptureRefundRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('exceeds the refundable balance'),
                $this->isType('array')
            );

        $this->orderTransactionCaptureRepository->expects($this->never())->method('create');
        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('create');

        $this->service->handle($data, $context);
    }

    public function testHandleRejectsRefundExceedingRemainingBalanceAfterPriorRefund(): void
    {
        // 60.00 transaction, 50.00 already refunded -> only 10.00 remains.
        $data = [
            'id' => 'refund-2',
            'transaction_reference' => 'trans-1',
            'amount' => 2000, // 20.00 requested
        ];
        $context = Context::createDefaultContext();

        $priorRefund = new OrderTransactionCaptureRefundEntity();
        $priorRefund->setId('prior-refund');
        $priorRefund->setAmount($this->price(50.00));
        $priorState = new StateMachineStateEntity();
        $priorState->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_COMPLETED);
        $priorRefund->setStateMachineState($priorState);

        $priorCapture = new OrderTransactionCaptureEntity();
        $priorCapture->setId('capture-prior');
        $priorCapture->setExternalReference('refund-1-trans-1');
        $priorCapture->setAmount($this->price(50.00));
        $priorCapture->setRefunds(new OrderTransactionCaptureRefundCollection([$priorRefund]));

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(60.00));
        $this->attachCurrency($transaction);
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$priorCapture]));

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->orderTransactionCaptureRefundRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('exceeds the refundable balance'), $this->isType('array'));

        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('create');

        $this->service->handle($data, $context);
    }

    public function testHandleIgnoresFailedRefundsWhenComputingRefundableBalance(): void
    {
        // 60.00 transaction with a 50.00 FAILED refund -> the full 60.00 is still
        // refundable, so a 20.00 refund must be accepted.
        $data = [
            'id' => 'refund-2',
            'transaction_reference' => 'trans-1',
            'amount' => 2000, // 20.00
        ];
        $context = Context::createDefaultContext();

        $failedRefund = new OrderTransactionCaptureRefundEntity();
        $failedRefund->setId('failed-refund');
        $failedRefund->setAmount($this->price(50.00));
        $failedState = new StateMachineStateEntity();
        $failedState->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_FAILED);
        $failedRefund->setStateMachineState($failedState);

        $priorCapture = new OrderTransactionCaptureEntity();
        $priorCapture->setId('capture-prior');
        $priorCapture->setExternalReference('refund-1-trans-1');
        $priorCapture->setAmount($this->price(50.00));
        $priorCapture->setRefunds(new OrderTransactionCaptureRefundCollection([$failedRefund]));

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(60.00));
        $this->attachCurrency($transaction);
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$priorCapture]));

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->initialStateIdLoader->method('get')->willReturn('state-id');

        $this->orderTransactionCaptureRefundRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        $this->orderTransactionCaptureRepository->expects($this->once())->method('create');
        $this->orderTransactionCaptureRefundRepository->expects($this->once())->method('create');

        $this->service->handle($data, $context);
    }

    public function testHandleAbortsWhenOrderCurrencyCannotBeResolved(): void
    {
        // No order/currency loaded -> must fail closed, never assume a currency.
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 1000,
        ];
        $context = Context::createDefaultContext();

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(100.00));

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Unable to resolve the order currency'), $this->isType('array'));

        $this->orderTransactionCaptureRepository->expects($this->never())->method('create');
        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('create');

        $this->service->handle($data, $context);
    }

    /**
     * The refundable-balance guard must hold in every currency, not just NGN.
     * In a 0-decimal currency the minor unit *is* the major unit.
     */
    public function testHandleRejectsRefundExceedingTransactionTotalInZeroDecimalCurrency(): void
    {
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 700, // 700 JPY (0-decimal) vs a 60 JPY transaction
        ];
        $context = Context::createDefaultContext();

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(60.00));
        $transaction->setCaptures(new OrderTransactionCaptureCollection([]));
        $this->attachCurrency($transaction, 'JPY');

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->orderTransactionCaptureRefundRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('exceeds the refundable balance'), $this->isType('array'));

        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('create');

        $this->service->handle($data, $context);
    }

    public function testHandleRejectsRefundWhenAmountDoesNotMatchCapture(): void
    {
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 1000, // 10.00 NGN
        ];
        $context = Context::createDefaultContext();

        $externalReference = 'refund-1-trans-1';
        $capture = new OrderTransactionCaptureEntity();
        $capture->setId('capture-id-999');
        $capture->setExternalReference($externalReference);
        // Capture is for 5.00 NGN — the webhook claims a 10.00 NGN refund.
        $capture->setAmount($this->price(5.00));

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(100.00)); // refundable base
        $this->attachCurrency($transaction);
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('does not match the capture amount'),
                $this->isType('array')
            );

        $this->orderTransactionCaptureRepository->expects($this->never())->method('create');
        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('create');

        $this->service->handle($data, $context);
    }

    /**
     * The currency must be resolvable on the transaction: the service fails
     * closed rather than converting amounts with an assumed currency.
     */
    private function attachCurrency(OrderTransactionEntity $transaction, string $isoCode = 'NGN'): void
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode($isoCode);
        $order = new OrderEntity();
        $order->setCurrency($currency);
        $transaction->setOrder($order);
    }

    private function price(float $amount): CalculatedPrice
    {
        return new CalculatedPrice(
            $amount,
            $amount,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        );
    }

    public function testHandleCreateCaptureFailure(): void
    {
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 1000,
        ];
        $context = Context::createDefaultContext();

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(100.00)); // refundable base
        $this->attachCurrency($transaction);

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->orderTransactionCaptureRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        $this->orderTransactionCaptureRepository->method('create')
            ->willThrowException(new \Exception('DB error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to create capture'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create capture for transaction "trans-id-123".');

        $this->service->handle($data, $context);
    }

    public function testHandleCreateRefundWhenIdAlreadyExists(): void
    {
        $data = [
            'id' => 'refund-1',
            'transaction_reference' => 'trans-1',
            'amount' => 1000,
        ];
        $context = Context::createDefaultContext();

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setAmount($this->price(100.00)); // refundable base
        $this->attachCurrency($transaction);

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $captureId = Uuid::fromStringToHex('paystack-capture-refund-1-trans-1');
        $this->orderTransactionCaptureRepository->method('searchIds')
            ->willReturn($this->createIdSearchResult([$captureId]));

        // refundExists returns false, but entityExists in createRefund returns true
        $this->orderTransactionCaptureRefundRepository->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturnOnConsecutiveCalls(
                $this->createEmptyIdSearchResult(),
                $this->createIdSearchResult(['some-id'])
            );

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Refund with ID already exists'));

        $this->service->handle($data, $context);
    }

    private function createEmptySearchResult(): EntitySearchResult
    {
        return new EntitySearchResult(
            OrderTransactionEntity::class,
            0,
            new OrderTransactionCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function createSearchResult(array $entities): EntitySearchResult
    {
        return new EntitySearchResult(
            OrderTransactionEntity::class,
            count($entities),
            new OrderTransactionCollection($entities),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function createEmptyIdSearchResult(): IdSearchResult
    {
        return new IdSearchResult(
            0,
            [],
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function createIdSearchResult(array $ids): IdSearchResult
    {
        $data = [];

        foreach ($ids as $id) {
            $data[$id] = ['primaryKey' => $id, 'data' => []];
        }

        return new IdSearchResult(
            count($ids),
            $data,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
