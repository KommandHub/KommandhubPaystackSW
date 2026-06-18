<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Presentation\Controller;

use Kommandhub\PaystackSW\Webhook\Application\Processor\WebhookProcessor;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], 'csrf_protected' => false])]
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookProcessor $webhookProcessor,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/paystack/webhook',
        name: 'frontend.paystack.webhook',
        methods: ['POST']
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
