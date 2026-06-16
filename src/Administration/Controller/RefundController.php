<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Administration\Controller;

use Kommandhub\PaystackSW\Service\Paystack;
use Shopware\Core\PlatformRequest;
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
        private readonly Paystack $paystack
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
    public function refund(Request $request): JsonResponse
    {
        $transactionReference = $request->request->get('transaction');

        if (!$transactionReference) {
            return new JsonResponse([
                'error' => 'Transaction reference is required',
            ], 400);
        }

        $amount = $request->request->get('amount'); // optional partial refund
        $reason = $request->request->get('reason', 'Refund initiated from shop administration'); // optional reason

        $payload = [
            'transaction' => (string)$transactionReference,
            'amount' => $amount,
            'reason' => (string)$reason,
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
