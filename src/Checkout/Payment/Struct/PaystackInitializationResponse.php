<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Struct;

use Shopware\Core\Framework\Struct\Struct;

class PaystackInitializationResponse extends Struct
{
    public function __construct(
        protected string $authorizationUrl,
        protected string $reference,
        protected ?string $accessCode = null,
    ) {
    }

    public function getAuthorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getAccessCode(): ?string
    {
        return $this->accessCode;
    }
}
