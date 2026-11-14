<?php

declare(strict_types=1);

namespace Theocode\Wise\Resources\Simulation\Models;

use Theocode\Wise\Contracts\Hydratable;
use Theocode\Wise\Hydration\Cast;

final readonly class BalanceTopUp implements Hydratable
{
    /**
     * @param  list<SimulatedBalance>  $balancesAfter
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $transactionId = null,
        public ?string $state = null,
        public array $balancesAfter = [],
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $balancesAfter = [];
        foreach (Cast::list($data, 'balancesAfter') as $item) {
            $balancesAfter[] = SimulatedBalance::fromArray($item);
        }

        return new self(
            transactionId: Cast::int($data, 'transactionId'),
            state: Cast::string($data, 'state'),
            balancesAfter: $balancesAfter,
            raw: $data,
        );
    }
}
