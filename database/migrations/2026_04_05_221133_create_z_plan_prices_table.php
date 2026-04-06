<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->string('billing_cycle');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['plan_id', 'currency', 'billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};