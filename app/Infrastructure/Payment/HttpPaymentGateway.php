<?php

declare(strict_types=1);

namespace App\Payment;

use App\Application\Contract\PaymentGateway;
use App\Application\DTO\PaymentResultDto;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Money\Money;
use RuntimeException;

readonly class HttpPaymentGateway implements PaymentGateway
{
    public function charge(
        string $cardToken,
        Money $amount,
        string $idempotencyKey,
    ): PaymentResultDto {
        /** @var Response $response */
        $response = Http::withToken(
            (string) config('services.payment.secret')
        )
            ->timeout(10)
            ->post(
                (string) config('services.payment.url'),
                [
                    'card_token' => $cardToken,
                    'amount' => $amount->getAmount(),
                    'currency' => 'PLN',
                    'request_id' => $idempotencyKey,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException('Payment request failed.');
        }

        if ($response->json('status') !== 'ok') {
            throw new RuntimeException('Payment was rejected.');
        }

        $paymentId = $response->json('payment_id');

        if (!is_string($paymentId) || $paymentId === '') {
            throw new RuntimeException('Payment ID is missing.');
        }

        return new PaymentResultDto(
            paymentId: $paymentId,
        );
    }
}
