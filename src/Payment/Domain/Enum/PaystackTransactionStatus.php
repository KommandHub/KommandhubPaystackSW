<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Domain\Enum;

enum PaystackTransactionStatus: string
{
    case SUCCESS = 'success';
    case ABANDONED = 'abandoned';
    case FAILED = 'failed';
    case REVERSED = 'reversed';
    case ONGOING = 'ongoing';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case QUEUED = 'queued';

    public function isFinal(): bool
    {
        return match ($this) {
            self::SUCCESS, self::ABANDONED, self::FAILED, self::REVERSED => true,
            default => false,
        };
    }
}
