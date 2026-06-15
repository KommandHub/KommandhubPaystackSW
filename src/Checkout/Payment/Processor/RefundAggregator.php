<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Processor;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;

/**
 * @final
 */
final class RefundAggregator
{
    /**
     * Aggregates refund data for an order transaction.
     * Calculates the total amount refunded across all captures and determines
     * if each capture and the overall transaction are fully refunded.
     *
     * @param OrderTransactionEntity $transaction The transaction to aggregate refunds for.
     * @param string $currentRefundId The ID of the refund currently being processed to include it in calculation.
     *
     * @return RefundAggregationResult The aggregated refund details.
     */
    public function aggregate(OrderTransactionEntity $transaction, string $currentRefundId): RefundAggregationResult
    {
        $captures = [];
        $totalRefunded = 0;
        $totalAmount = (int) round($transaction->getAmount()->getTotalPrice() * 100);

        foreach ($transaction->getCaptures() as $capture) {
            $captureTotal = (int) round($capture->getAmount()->getTotalPrice() * 100);
            $captureRefunded = 0;

            foreach ($capture->getRefunds() as $refund) {
                if ($refund->getStateMachineState()?->getTechnicalName() === OrderTransactionCaptureRefundStates::STATE_COMPLETED
                    || $refund->getId() === $currentRefundId
                ) {
                    $captureRefunded += (int) round($refund->getAmount()->getTotalPrice() * 100);
                }
            }

            $captures[$capture->getId()] = (object)[
                'isFullyRefunded' => $captureRefunded >= $captureTotal,
            ];

            $totalRefunded += $captureRefunded;
        }

        return new RefundAggregationResult(
            $captures,
            $totalRefunded >= $totalAmount
        );
    }
}