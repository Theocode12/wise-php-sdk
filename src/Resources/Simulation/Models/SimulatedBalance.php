<?php

declare(strict_types=1);

namespace Theocode\Wise\Resources\Simulation\Models;

use Theocode\Wise\Contracts\Hydratable;
use Theocode\Wise\Hydration\Cast;

final readonly class SimulatedBalance implements Hydratable
{
    public function __construct(
        public ?int $id = null,
        public ?float $value = null,
        public ?string $currency = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new self(
            id: Cast::int($data, 'id'),
            value: Cast::float($data, 'value'),
            currency: Cast::string($data, 'currency'),
        );
    }
}
