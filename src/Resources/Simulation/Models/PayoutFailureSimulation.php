<?php

declare(strict_types=1);

namespace Theocode\Wise\Resources\Simulation\Models;

use Theocode\Wise\Contracts\Hydratable;
use Theocode\Wise\Hydration\Cast;

/**
 * Payout-failure simulation response.
 *
 * Wise documents no response body for this endpoint ("the payout failure webhook
 * is asynchronous") but notes that "a failure code is selected at random and
 * returned" when no code is sent. This model captures the code when present and
 * keeps the raw payload, tolerating empty or varying response shapes.
 */
final readonly class PayoutFailureSimulation implements Hydratable
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $failureReasonCode = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $code = Cast::string($data, 'failure_reason_code')
            ?? Cast::string($data, 'failureReasonCode')
            ?? Cast::string($data, 'failureCode')
            ?? Cast::string($data, 'code');

        return new self(
            failureReasonCode: $code,
            raw: $data,
        );
    }
}
