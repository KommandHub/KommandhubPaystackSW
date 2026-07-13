<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Administration\Controller;

use Kommandhub\PaystackSW\Util\PaystackConstants;
use Kommandhub\PaystackSW\Util\PaystackCurrencyHelper;
use Kommandhub\PaystackSW\Checkout\Payment\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Client\PaystackClient;
use Kommandhub\PaystackSW\Setting\Service\Config;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class RefundController.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class RefundController extends AbstractController
{
    /**
     * RefundController constructor.
     *
     * @param PaystackClient $paystack
     * @param OrderTransactionService $orderTransactionService
     */
    public function __construct(
        private readonly PaystackClient $paystack,
        private readonly OrderTransactionService $orderTransactionService,
        private readonly Config $config
    ) {
    }

    /**
     * Handles the refund request from the administration.
     *
     * @param Request $request
     * @param Context $context
     *
     * @return JsonResponse
     */
    #[Route(
        path: '/api/_action/paystack/refund',
        name: 'api.action.paystack.refund',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function refund(Request $request, Context $context): JsonResponse
    {
        $transactionReference = $request->request->get('transaction');

        if (!is_string($transactionReference) || $transactionReference === '') {
            return $this->errorResponse('Transaction reference is required');
        }

        $transaction = $this->orderTransactionService->findOneByPaystackReference($transactionReference, $context);

        if ($transaction === null) {
            return $this->errorResponse('Refundable transaction not found for the provided reference'); // @codeCoverageIgnore
        }

        if (!$this->isRefundableTransaction($transaction)) {
            return $this->errorResponse('Transaction is not in a refundable state');
        }

        $salesChannelId = $transaction->getOrder()?->getSalesChannelId();

        if (!$this->config->getBool('refundEnabled', $salesChannelId)) {
            return $this->errorResponse('Refund feature is currently disabled');
        }

        $amount = $request->request->get('amount');

        if ($amount !== null && (!is_numeric($amount) || (float)$amount <= 0.0)) {
            return $this->errorResponse('Refund amount must be a positive number'); // @codeCoverageIgnore
        }

        $reason = $request->request->get('reason', 'Refund initiated from shop administration');
        $customerNote = $request->request->get('customer_note');
        $merchantNote = $request->request->get('merchant_note');

        $payload = [
            'transaction' => (string)$transactionReference,
            'reason' => (string)$reason,
        ];

        if ($amount !== null) {
            $currencyIso = $transaction->getOrder()?->getCurrency()?->getIsoCode() ?? 'NGN';
            $minorAmount = PaystackCurrencyHelper::toMinorUnit((float)$amount, $currencyIso);

            $minAmountLimit = $this->config->get('minimumRefundAmount', PaystackConstants::MINIMUM_REFUND_AMOUNT, $salesChannelId);
            $minAmountLimit = (int) $minAmountLimit;

            if ($minorAmount < $minAmountLimit) {
                return $this->errorResponse(sprintf(
                    'Refund amount must be at least %s %s',
                    PaystackCurrencyHelper::fromMinorUnit($minAmountLimit, $currencyIso),
                    $currencyIso
                ));
            }

            $payload['amount'] = $minorAmount;
        }

        if (is_string($customerNote) && trim($customerNote) !== '') {
            $payload['customer_note'] = $customerNote;
        }

        if (is_string($merchantNote) && trim($merchantNote) !== '') {
            $payload['merchant_note'] = $merchantNote;
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

        return in_array($state, [
            OrderTransactionStates::STATE_PAID,
            OrderTransactionStates::STATE_PARTIALLY_PAID,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
        ], true);
    }

    private function errorResponse(string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
        ], 400);
    }
}
