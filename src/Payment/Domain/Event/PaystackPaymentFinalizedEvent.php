<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Domain\Event;

use Kommandhub\Foundation\Event\PaymentFinalizedEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;

class PaystackPaymentFinalizedEvent extends PaymentFinalizedEvent implements ShopwareEvent
{
}
