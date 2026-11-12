<?php

declare(strict_types=1);

namespace Theocode\Wise\Resources\Simulation\Enums;

enum SimulatedTransferStatus: string
{
    case Processing = 'processing';
    case FundsConverted = 'funds_converted';
    case OutgoingPaymentSent = 'outgoing_payment_sent';
    case BouncedBack = 'bounced_back';
    case FundsRefunded = 'funds_refunded';

    /**
     * @return list<self>
     */
    public static function chain(): array
    {
        return [
            self::Processing,
            self::FundsConverted,
            self::OutgoingPaymentSent,
            self::BouncedBack,
            self::FundsRefunded,
        ];
    }
}
