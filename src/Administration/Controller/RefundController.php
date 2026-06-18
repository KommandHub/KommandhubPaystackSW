<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Administration\Controller;

use Kommandhub\PaystackSW\Payment\Application\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Paystack;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class RefundController.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class RefundController extends AbstractController
{
    /**
     * RefundController constructor.
     *
     * @param Paystack $paystack
     */
    public function __construct(
        private readonly Paystack $paystack,
        private readonly OrderTransactionService $orderTransactionService
    ) {
    }

    /**
     * Handles the refund request from the administration.
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @Route(
     *     path="/api/_action/paystack/refund",
     *     name="api.action.paystack.refund",
     *     methods={"POST"}
     * )
     */
    #[Route(
        path: '/api/_action/paystack/refund',
        name: 'api.action.paystack.refund',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: ['POST']
    )]
    public function refund(Request $request, Context $context): JsonResponse
    {
        $transactionReference = $request->request->get('transaction');

        if (!is_string($transactionReference) || $transactionReference === '') {
            return $this->errorResponse('Transaction reference is required');
        }

        $transaction = $this->orderTransactionService->findOneByPaystackReference($transactionReference, $context);

        if ($transaction === null) {
            return $this->errorResponse('Refundable transaction not found for the provided reference');
        }

        if (!$this->isRefundableTransaction($transaction)) {
            return $this->errorResponse('Transaction is not in a refundable state');
        }

        $amount = $request->request->get('amount');

        if ($amount !== null && (!is_numeric($amount) || (float)$amount <= 0.0)) {
            return $this->errorResponse('Refund amount must be a positive number');
        }

        $reason = $request->request->get('reason', 'Refund initiated from shop administration');

        $payload = [
            'transaction' => (string)$transactionReference,
            'reason' => (string)$reason,
        ];

        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        try {
            $response = $this->paystack->refunds()->create($payload);

            return new JsonResponse($response);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    private function isRefundableTransaction(OrderTransactionEntity $transaction): bool
    {
        $state = $transaction->getStateMachineState()?->getTechnicalName();

        if (!in_array($state, [
            OrderTransactionStates::STATE_PAID,
            OrderTransactionStates::STATE_PARTIALLY_PAID,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
        ], true)) {
            return false;
        }

        $captures = $transaction->getCaptures();

        return $captures !== null && $captures->count() > 0;
    }

    private function errorResponse(string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
        ], 400);
    }
}
