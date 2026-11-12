<?php

declare(strict_types=1);

namespace Theocode\Wise\Resources\Simulation\Requests;

use Theocode\Wise\Exceptions\ValidationException;

final readonly class BalanceTopUpRequest
{
    public function __construct(
        public int $profileId,
        public int $balanceId,
        public string $currency,
        public float $amount,
        public ?string $channel = null,
    ) {
        if ($this->profileId <= 0) {
            throw new ValidationException('profileId must be greater than zero.');
        }

        if ($this->balanceId <= 0) {
            throw new ValidationException('balanceId must be greater than zero.');
        }

        if (preg_match('/^[A-Z]{3}$/i', $this->currency) !== 1) {
            throw new ValidationException('currency must be a valid 3-letter currency code.');
        }

        if ($this->amount <= 0) {
            throw new ValidationException('amount must be greater than zero.');
        }

        if ($this->channel !== null && ! in_array(strtoupper($this->channel), ['TRANSFER', 'CARD'], true)) {
            throw new ValidationException('channel must be TRANSFER or CARD.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'profileId' => $this->profileId,
            'balanceId' => $this->balanceId,
            'currency' => strtoupper($this->currency),
            'amount' => $this->amount,
        ];

        if ($this->channel !== null) {
            $data['channel'] = strtoupper($this->channel);
        }

        return $data;
    }
}
