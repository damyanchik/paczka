<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->dateTime('renewal_at');
            $table->string('idempotency_key')->unique();
            $table->string('status', 30);
            $table->string('provider_payment_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique([
                'subscription_id',
                'renewal_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewals');
    }
};
