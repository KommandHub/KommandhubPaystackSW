<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Controller;

use Kommandhub\PaystackSW\Webhook\Service\WebhookProcessor;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/*
 * We use `storefront` scope otherwise Paystack will be required to have authorization to call the webhook endpoint,
 * which is not possible. By setting the scope to `storefront`, we allow unauthenticated requests to this endpoint
 * while still keeping it within the context of the storefront, which is appropriate for webhook processing.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookProcessor $webhookProcessor,
        private readonly ConfigurableLogger $logger
    ) {
    }

    #[Route(
        path: '/paystack/webhook',
        name: 'frontend.paystack.webhook',
        methods: [Request::METHOD_POST]
    )]
    public function execute(Request $request, Context $context): Response
    {
        try {
            $this->webhookProcessor->process($request, $context);

            return new Response('', Response::HTTP_OK);
        } catch (AccessDeniedHttpException $exception) {
            $this->logger->warning('[Paystack] Webhook signature validation failed.', [
                'message' => $exception->getMessage(),
                'ip' => $request->getClientIp(),
            ]);

            return new Response('', Response::HTTP_FORBIDDEN);
        } catch (BadRequestHttpException $exception) {
            $this->logger->warning('[Paystack] Invalid webhook payload received.', [
                'message' => $exception->getMessage(),
                'ip' => $request->getClientIp(),
            ]);

            return new Response('', Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            $this->logger->error('[Paystack] Webhook processing failed.', [
                'exception' => $exception,
            ]);

            return new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
