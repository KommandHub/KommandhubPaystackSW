<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Application\Processor;

/**
 * @final
 */
final readonly class RefundAggregationResult
{
    /**
     * @param array<string, object> $captures
     * @param bool $isFullyRefunded
     */
    public function __construct(
        public array $captures,
        public bool $isFullyRefunded,
    ) {
    }
}
