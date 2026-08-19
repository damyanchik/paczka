<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('promo_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('promo_code_id')
                ->constrained('promo_codes')
                ->cascadeOnDelete();

            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            $table->string('email', 30);

            $table->timestamp('used_at');

            $table->timestamps();

            $table->unique([
                'promo_code_id',
                'cart_id',
            ]);
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('promo_usages');
    }
};
