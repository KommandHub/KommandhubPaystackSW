<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service\Webhook;

use Kommandhub\PaystackSW\Service\Webhook\RefundInitializeService;
use Kommandhub\PaystackSW\Util\PaystackCurrencyHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;

#[CoversClass(RefundInitializeService::class)]
#[UsesClass(PaystackCurrencyHelper::class)]
class RefundInitializeServiceTest extends TestCase
{
    private EntityRepository&MockObject $orderTransactionRepository;
    private EntityRepository&MockObject $orderTransactionCaptureRepository;
    private EntityRepository&MockObject $orderTransactionCaptureRefundRepository;
    private InitialStateIdLoader&MockObject $initialStateIdLoader;
    private LoggerInterface&MockObject $logger;
    private RefundInitializeService $service;

    protected function setUp(): void
    {
        $this->orderTransactionRepository = $this->createMock(EntityRepository::class);
        $this->orderTransactionCaptureRepository = $this->createMock(EntityRepository::class);
        $this->orderTransactionCaptureRefundRepository = $this->createMock(EntityRepository::class);
        $this->initialStateIdLoader = $this->createMock(InitialStateIdLoader::class);
        $this->logger = $this->createMock(LoggerInterface::class);

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

        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
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

        $this->orderTransactionRepository->method('search')
            ->willReturn($this->createSearchResult([$transaction]));

        $this->orderTransactionCaptureRepository->method('searchIds')
            ->willReturn($this->createEmptyIdSearchResult());

        $this->orderTransactionCaptureRepository->method('create')
            ->willThrowException(new \Exception('DB error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to create capture'));

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
