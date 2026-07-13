<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

abstract class WebhookEvent extends Event implements ShopwareEvent
{
    public function __construct(
        protected readonly array $data,
        protected readonly Context $context
    ) {
    }

    abstract public function getWebhookName(): string;

    public function getData(): array
    {
        return $this->data;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
