<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Administration\Controller;

use Kommandhub\PaystackSW\Service\Paystack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class RefundController extends AbstractController
{
    public function __construct(
        private readonly Paystack $paystack
    ) {
    }

    #[Route(
        path: '/api/_action/paystack/refund',
        name: 'api.action.paystack.refund',
        methods: ['POST']
    )]
    public function refund(Request $request): JsonResponse
    {
        $transactionReference = $request->request->get('transaction');

        if (!$transactionReference) {
            return new JsonResponse([
                'error' => 'Transaction reference is required'
            ], 400);
        }

        $amount = $request->request->get('amount'); // optional partial refund
        $reason = $request->request->get('reason', 'Shopware refund');

        $payload = [
            'transaction' => $transactionReference,
            'amount' => $amount,
            'reason' => $reason,
        ];

        try {
            $response = $this->paystack->refunds()->create($payload);

            return new JsonResponse($response);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}