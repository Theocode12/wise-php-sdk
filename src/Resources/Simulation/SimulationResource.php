<?php

declare(strict_types=1);

namespace Theocode\Wise\Resources\Simulation;

use Theocode\Wise\Config\ClientConfig;
use Theocode\Wise\Exceptions\ValidationException;
use Theocode\Wise\Resources\Resource;
use Theocode\Wise\Resources\Simulation\Enums\SimulatedTransferStatus;
use Theocode\Wise\Resources\Simulation\Models\BalanceTopUp;
use Theocode\Wise\Resources\Simulation\Models\PayoutFailureSimulation;
use Theocode\Wise\Resources\Simulation\Requests\BalanceTopUpRequest;
use Theocode\Wise\Resources\Transfer\Models\Transfer;

/**
 * Sandbox-only simulation endpoints.
 *
 * Every method throws unless the client targets the Wise sandbox, to prevent
 * accidental production calls.
 */
final class SimulationResource extends Resource
{
    public function transferState(int $transferId, SimulatedTransferStatus $status): Transfer
    {
        $this->ensureSandbox();

        $statusValue = $status->value;
        $payload = $this->client->request('GET', "/v1/simulation/transfers/{$transferId}/{$statusValue}");

        return Transfer::fromArray($payload);
    }

    public function payoutFailure(int $transferId, ?string $failureReasonCode = null): PayoutFailureSimulation
    {
        $this->ensureSandbox();

        $body = $failureReasonCode === null ? [] : ['failure_reason_code' => $failureReasonCode];
        $payload = $this->client->request('POST', "/v1/simulation/transfers/{$transferId}/payout-failure", body: $body);

        return PayoutFailureSimulation::fromArray($payload);
    }

    public function balanceTopUp(BalanceTopUpRequest $request): BalanceTopUp
    {
        $this->ensureSandbox();

        $payload = $this->client->request('POST', '/v1/simulation/balance/topup', body: $request->toArray());

        return BalanceTopUp::fromArray($payload);
    }

    /**
     * Walk a transfer through the documented simulation chain up to the target state.
     *
     * Chain: processing -> funds_converted -> outgoing_payment_sent -> bounced_back -> funds_refunded.
     * Wise processes simulations asynchronously, so wait at least 5 seconds between state
     * changes in real runs. This helper performs no waiting itself — pass a $sleeper
     * (e.g. fn () => sleep(5)) to wait between steps, or wait between manual
     * transferState() calls.
     *
     * @param  callable(): void|null  $sleeper  Invoked between steps when provided.
     */
    public function transferThrough(
        int $transferId,
        SimulatedTransferStatus $target,
        ?callable $sleeper = null,
    ): Transfer {
        $this->ensureSandbox();

        $transfer = null;
        foreach (SimulatedTransferStatus::chain() as $status) {
            $transfer = $this->transferState($transferId, $status);

            if ($status === $target) {
                break;
            }

            if ($sleeper !== null) {
                $sleeper();
            }
        }

        /** @var Transfer $transfer */
        return $transfer;
    }

    private function ensureSandbox(): void
    {
        $baseUrl = rtrim($this->client->config->baseUrl, '/');

        if ($baseUrl === ClientConfig::SANDBOX_BASE_URL) {
            return;
        }

        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        if ($host !== '' && str_ends_with(strtolower($host), 'wise-sandbox.com')) {
            return;
        }

        throw new ValidationException('Simulation endpoints are sandbox only.');
    }
}
