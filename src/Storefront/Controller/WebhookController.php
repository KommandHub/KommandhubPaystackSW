<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Storefront\Controller;

use Kommandhub\PaystackSW\Service\Webhook\WebhookProcessor;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class WebhookController extends StorefrontController
{
    public function __construct(
        private readonly WebhookProcessor $webhookProcessor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Paystack webhook endpoint.
     *
     * Notes:
     * - Paystack may retry webhook deliveries.
     * - Processing should therefore be idempotent.
     * - Signature verification is delegated to WebhookProcessor.
     */
    #[Route(
        path: '/paystack/webhook',
        name: 'frontend.paystack.webhook',
        defaults: ['csrf_protected' => false],
        methods: ['POST', 'GET']
    )]
    public function execute(Request $request, Context $context): Response
    {
        try {
            $this->webhookProcessor->process(
                $request,
                $context
            );

            return new Response(
                '',
                Response::HTTP_OK
            );
        } catch (AccessDeniedHttpException $exception) {
            $this->logger->warning(
                '[Paystack] Webhook signature validation failed.',
                [
                    'message' => $exception->getMessage(),
                    'ip' => $request->getClientIp(),
                ]
            );

            return new Response(
                '',
                Response::HTTP_FORBIDDEN
            );
        } catch (BadRequestHttpException $exception) {
            $this->logger->warning(
                '[Paystack] Invalid webhook payload received.',
                [
                    'message' => $exception->getMessage(),
                    'ip' => $request->getClientIp(),
                ]
            );

            return new Response(
                '',
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                '[Paystack] Webhook processing failed.',
                [
                    'exception' => $exception,
                ]
            );

            return new Response(
                '',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
