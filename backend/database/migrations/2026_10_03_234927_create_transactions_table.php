<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->unique();
            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('subtotal');
            $table->enum('discount_type', ['none', 'nominal', 'percent'])->default('none');
            $table->unsignedInteger('discount_value')->default(0);
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('total');
            $table->enum('payment_method', ['cash', 'qris', 'transfer']);
            $table->unsignedInteger('amount_paid');
            $table->unsignedInteger('change_amount')->default(0);
            $table->enum('status', ['completed', 'voided'])->default('completed');
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};