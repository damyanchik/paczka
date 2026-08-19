<?php

declare(strict_types=1);

namespace App\Presentation\Console\Command;

use App\Application\Action\RenewSubscriptions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:renew')]
#[Description('Renew due subscriptions')]
class RenewSubscriptionsCommand extends Command
{
    public function handle(RenewSubscriptions $renewSubscriptions): int
    {
        $renewSubscriptions->execute();

        return self::SUCCESS;
    }
}
