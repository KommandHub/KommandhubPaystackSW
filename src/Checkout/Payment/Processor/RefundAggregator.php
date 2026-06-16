<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Processor;

use Kommandhub\PaystackSW\Util\PaystackCurrencyHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;

/**
 * @final
 */
class RefundAggregator
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
        $totalAmount = PaystackCurrencyHelper::toMinorUnit(
            $transaction->getAmount()->getTotalPrice(),
            $transaction->getOrder()?->getCurrency()?->getIsoCode() ?? 'NGN'
        );

        $capturesCollection = $transaction->getCaptures();

        if ($capturesCollection !== null) {
            foreach ($capturesCollection as $capture) {
                $captureTotal = PaystackCurrencyHelper::toMinorUnit(
                    $capture->getAmount()->getTotalPrice(),
                    $transaction->getOrder()?->getCurrency()?->getIsoCode() ?? 'NGN'
                );
                $captureRefunded = 0;

                $refundsCollection = $capture->getRefunds();

                if ($refundsCollection !== null) {
                    foreach ($refundsCollection as $refund) {
                        if ($refund->getStateMachineState()?->getTechnicalName() === OrderTransactionCaptureRefundStates::STATE_COMPLETED
                            || $refund->getId() === $currentRefundId
                        ) {
                            $captureRefunded += PaystackCurrencyHelper::toMinorUnit(
                                $refund->getAmount()->getTotalPrice(),
                                $transaction->getOrder()?->getCurrency()?->getIsoCode() ?? 'NGN'
                            );
                        }
                    }
                }

                $captures[$capture->getId()] = (object)[
                    'isFullyRefunded' => $captureRefunded >= $captureTotal,
                ];

                $totalRefunded += $captureRefunded;
            }
        }

        return new RefundAggregationResult(
            $captures,
            $totalRefunded >= $totalAmount
        );
    }
}
