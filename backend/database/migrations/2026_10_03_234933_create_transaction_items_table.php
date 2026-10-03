<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name', 150);
            $table->unsignedInteger('price_buy');
            $table->unsignedInteger('price_sell');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('subtotal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};