<?php

declare(strict_types=1);

namespace Theocode\Wise\Resources\Transfer\Models;

use Theocode\Wise\Contracts\Hydratable;
use Theocode\Wise\Hydration\Cast;
use Theocode\Wise\Resources\Transfer\Enums\TransferStatus;

final readonly class Transfer implements Hydratable
{
    public function __construct(
        public int $id,
        public int $targetAccount,
        public ?string $quoteUuid,
        public string $status,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            id: Cast::int($data, 'id', 0) ?? 0,
            targetAccount: Cast::int($data, 'targetAccount', 0) ?? 0,
            quoteUuid: Cast::string($data, 'quoteUuid'),
            status: strtolower(Cast::string($data, 'status', '') ?? ''),
        );
    }

    public function statusEnum(): ?TransferStatus
    {
        return TransferStatus::tryFrom($this->status);
    }

    public function isCompleted(): bool
    {
        $status = $this->statusEnum();

        return $status === TransferStatus::OutgoingPaymentSent;
    }

    public function isPending(): bool
    {
        $status = $this->statusEnum();

        return $status === TransferStatus::IncomingPaymentWaiting
            || $status === TransferStatus::IncomingPaymentInitiated
            || $status === TransferStatus::Processing
            || $status === TransferStatus::FundsConverted;
    }

    public function isCancelled(): bool
    {
        $status = $this->statusEnum();

        return $status === TransferStatus::Cancelled
            || $status === TransferStatus::FundsRefunded
            || $status === TransferStatus::BouncedBack
            || $status === TransferStatus::ChargedBack;
    }
}
