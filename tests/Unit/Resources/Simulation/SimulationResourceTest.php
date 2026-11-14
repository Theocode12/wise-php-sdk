<?php

declare(strict_types=1);

namespace Theocode\Wise\Tests\Unit\Resources\Simulation;

use PHPUnit\Framework\TestCase;
use Theocode\Wise\Config\ClientConfig;
use Theocode\Wise\Exceptions\ValidationException;
use Theocode\Wise\Resources\Simulation\Enums\SimulatedTransferStatus;
use Theocode\Wise\Resources\Simulation\Requests\BalanceTopUpRequest;
use Theocode\Wise\Tests\Support\FakeTransport;
use Theocode\Wise\Tests\Support\Psr7Factory;
use Theocode\Wise\Tests\Support\TestClientFactory;

final class SimulationResourceTest extends TestCase
{
    public function test_transfer_state_hits_sandbox_path(): void
    {
        $fixture = file_get_contents(__DIR__.'/../../../Fixtures/wise/simulation_transfer.json');
        $transport = new FakeTransport([Psr7Factory::response(200, (string) $fixture)]);
        $client = TestClientFactory::make($transport, ClientConfig::apiToken('test-token', ClientConfig::SANDBOX_BASE_URL));

        $transfer = $client->simulation()->transferState(3001, SimulatedTransferStatus::Processing);

        self::assertSame(3001, $transfer->id);
        self::assertSame('processing', $transfer->status);
        self::assertSame('GET', $transport->lastRequest()->getMethod());
        self::assertSame('/v1/simulation/transfers/3001/processing', $transport->lastRequest()->getUri()->getPath());
    }

    public function test_production_base_url_throws_before_http(): void
    {
        $transport = new FakeTransport([]);
        $client = TestClientFactory::make($transport, ClientConfig::apiToken('test-token', ClientConfig::DEFAULT_BASE_URL));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sandbox only');

        $client->simulation()->transferState(3001, SimulatedTransferStatus::Processing);
        self::assertCount(0, $transport->requests());
    }

    public function test_custom_sandbox_host_suffix_is_allowed(): void
    {
        $fixture = file_get_contents(__DIR__.'/../../../Fixtures/wise/simulation_transfer.json');
        $transport = new FakeTransport([Psr7Factory::response(200, (string) $fixture)]);
        $client = TestClientFactory::make($transport, ClientConfig::apiToken('test-token', 'https://api.my-wise-sandbox.com'));

        $client->simulation()->transferState(3001, SimulatedTransferStatus::FundsConverted);

        self::assertSame('/v1/simulation/transfers/3001/funds_converted', $transport->lastRequest()->getUri()->getPath());
    }

    public function test_payout_failure_sends_code_and_hydrates(): void
    {
        $fixture = file_get_contents(__DIR__.'/../../../Fixtures/wise/simulation_payout_failure.json');
        $transport = new FakeTransport([Psr7Factory::response(200, (string) $fixture)]);
        $client = TestClientFactory::make($transport, ClientConfig::apiToken('test-token', ClientConfig::SANDBOX_BASE_URL));

        $result = $client->simulation()->payoutFailure(3001, 'ACCOUNT_CLOSED');

        self::assertSame('ACCOUNT_CLOSED', $result->failureReasonCode);
        self::assertSame('POST', $transport->lastRequest()->getMethod());
        self::assertSame('/v1/simulation/transfers/3001/payout-failure', $transport->lastRequest()->getUri()->getPath());
        self::assertStringContainsString('"failure_reason_code":"ACCOUNT_CLOSED"', (string) $transport->lastRequest()->getBody());
    }

    public function test_payout_failure_without_code_sends_no_body(): void
    {
        $transport = new FakeTransport([Psr7Factory::response(200, '{}')]);
        $client = TestClientFactory::make($transport, ClientConfig::apiToken('test-token', ClientConfig::SANDBOX_BASE_URL));

        $result = $client->simulation()->payoutFailure(3001);

        self::assertNull($result->failureReasonCode);
        self::assertSame('', (string) $transport->lastRequest()->getBody());
    }

    public function test_balance_top_up_sends_body_and_hydrates(): void
    {
        $fixture = file_get_contents(__DIR__.'/../../../Fixtures/wise/simulation_balance_topup.json');
        $transport = new FakeTransport([Psr7Factory::response(200, (string) $fixture)]);
        $client = TestClientFactory::make($transport, ClientConfig::apiToken('test-token', ClientConfig::SANDBOX_BASE_URL));

        $result = $client->simulation()->balanceTopUp(new BalanceTopUpRequest(
            profileId: 123,
            balanceId: 501,
            currency: 'gbp',
            amount: 150.0,
            channel: 'card',
        ));

        self::assertSame(9001, $result->transactionId);
        self::assertSame('COMPLETED', $result->state);
        self::assertCount(1, $result->balancesAfter);
        self::assertSame(501, $result->balancesAfter[0]->id);
        self::assertSame(150.0, $result->balancesAfter[0]->value);
        self::assertSame('GBP', $result->balancesAfter[0]->currency);
        self::assertSame('POST', $transport->lastRequest()->getMethod());
        self::assertSame('/v1/simulation/balance/topup', $transport->lastRequest()->getUri()->getPath());
        $body = (string) $transport->lastRequest()->getBody();
        self::assertStringContainsString('"profileId":123', $body);
        self::assertStringContainsString('"currency":"GBP"', $body);
        self::assertStringContainsString('"channel":"CARD"', $body);
    }

    public function test_balance_top_up_request_validation(): void
    {
        $this->expectException(ValidationException::class);
        new BalanceTopUpRequest(profileId: 0, balanceId: 501, currency: 'GBP', amount: 10.0);
    }

    public function test_balance_top_up_request_rejects_bad_channel(): void
    {
        $this->expectException(ValidationException::class);
        new BalanceTopUpRequest(profileId: 123, balanceId: 501, currency: 'GBP', amount: 10.0, channel: 'WIRE');
    }

    public function test_transfer_through_walks_chain_with_optional_sleeper(): void
    {
        $responses = [];
        foreach (['processing', 'funds_converted', 'outgoing_payment_sent'] as $status) {
            $responses[] = Psr7Factory::response(200, (string) json_encode(['id' => 3001, 'targetAccount' => 2001, 'status' => $status]));
        }
        $transport = new FakeTransport($responses);
        $client = TestClientFactory::make($transport, ClientConfig::apiToken('test-token', ClientConfig::SANDBOX_BASE_URL));

        $betweenSteps = 0;
        $transfer = $client->simulation()->transferThrough(
            3001,
            SimulatedTransferStatus::OutgoingPaymentSent,
            static function () use (&$betweenSteps): void {
                $betweenSteps++;
            },
        );

        self::assertSame('outgoing_payment_sent', $transfer->status);
        self::assertCount(3, $transport->requests());
        self::assertSame('/v1/simulation/transfers/3001/processing', $transport->requests()[0]->getUri()->getPath());
        self::assertSame('/v1/simulation/transfers/3001/funds_converted', $transport->requests()[1]->getUri()->getPath());
        self::assertSame('/v1/simulation/transfers/3001/outgoing_payment_sent', $transport->requests()[2]->getUri()->getPath());
        self::assertSame(2, $betweenSteps);
    }

    public function test_transfer_through_performs_no_waiting_by_default(): void
    {
        $responses = [];
        foreach (['processing', 'funds_converted'] as $status) {
            $responses[] = Psr7Factory::response(200, (string) json_encode(['id' => 3001, 'targetAccount' => 2001, 'status' => $status]));
        }
        $transport = new FakeTransport($responses);
        $client = TestClientFactory::make($transport, ClientConfig::apiToken('test-token', ClientConfig::SANDBOX_BASE_URL));

        $startedAt = hrtime(true);
        $transfer = $client->simulation()->transferThrough(3001, SimulatedTransferStatus::FundsConverted);
        $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;

        self::assertSame('funds_converted', $transfer->status);
        self::assertCount(2, $transport->requests());
        self::assertLessThan(1000, $elapsedMs, 'transferThrough must not sleep; waiting is caller-owned.');
    }

    public function test_simulated_transfer_status_enum_values(): void
    {
        self::assertSame('processing', SimulatedTransferStatus::Processing->value);
        self::assertSame('funds_converted', SimulatedTransferStatus::FundsConverted->value);
        self::assertSame('outgoing_payment_sent', SimulatedTransferStatus::OutgoingPaymentSent->value);
        self::assertSame('bounced_back', SimulatedTransferStatus::BouncedBack->value);
        self::assertSame('funds_refunded', SimulatedTransferStatus::FundsRefunded->value);
    }
}
