<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users');

            $table->foreignId('subscription_id')
                ->constrained('subscriptions');

            $table->foreignId('subscription_renewal_id')
                ->unique()
                ->constrained('subscription_renewals');

            $table->string('payment_id')->unique();
            $table->unsignedBigInteger('total_amount');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
