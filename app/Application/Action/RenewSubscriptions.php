<?php

declare(strict_types=1);

namespace App\Application\Action;

use App\Application\Contract\PaymentGateway;
use App\Domain\DTO\SubscriptionDto;
use App\Domain\Enum\SubscriptionRenewalStatusEnum;
use App\Infrastructure\Persistence\Eloquent\Repository\OrderRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\SubscriptionRenewalRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\SubscriptionRepository;
use Illuminate\Database\ConnectionInterface;
use Throwable;

readonly class RenewSubscriptions
{
    private const int RENEWAL_PERIOD_DAYS = 28;

    public function __construct(
        private ConnectionInterface $connection,
        private SubscriptionRepository $subscriptionRepository,
        private SubscriptionRenewalRepository $renewalRepository,
        private OrderRepository $orderRepository,
        private PaymentGateway $paymentGateway,
    ) {}

    public function execute(): void
    {
        foreach ($this->subscriptionRepository->findDue() as $subscription) {
            $this->renew($subscription);
        }
    }

    private function renew(SubscriptionDto $subscription): void
    {
        $idempotencyKey = sprintf(
            'subscription:%d:renewal:%s',
            $subscription->id,
            $subscription->nextRenewal->format('Y-m-d\TH:i:s'),
        );

        $renewal = $this->renewalRepository->getOrCreate(
            subscriptionId: $subscription->id,
            renewalAt: $subscription->nextRenewal,
            idempotencyKey: $idempotencyKey,
        );

        if ($renewal->status === SubscriptionRenewalStatusEnum::SUCCEED) {
            return;
        }

        try {
            $payment = $this->paymentGateway->charge(
                cardToken: $subscription->cardToken,
                amount: $subscription->price,
                idempotencyKey: $renewal->idempotencyKey,
            );
        } catch (Throwable $exception) {
            $this->renewalRepository->markFailed(
                renewalId: $renewal->id,
                error: $exception->getMessage(),
            );

            return;
        }

        $this->connection->transaction(function () use (
            $subscription,
            $renewal,
            $payment,
        ): void {
            $this->renewalRepository->markSucceeded(
                renewalId: $renewal->id,
                paymentId: $payment->paymentId,
            );

            $this->orderRepository->createForRenewal(
                userId: $subscription->userId,
                subscriptionId: $subscription->id,
                renewalId: $renewal->id,
                paymentId: $payment->paymentId,
                total: $subscription->price,
            );

            $this->subscriptionRepository->updateNextRenewal(
                subscriptionId: $subscription->id,
                nextRenewal: $subscription->nextRenewal->addDays(
                    self::RENEWAL_PERIOD_DAYS,
                ),
            );
        });
    }
}
