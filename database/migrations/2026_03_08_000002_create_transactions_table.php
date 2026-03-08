<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('public_id')->unique();   // pay_xxx
            $table->string('external_id')->nullable()->index(); // idempotency or client ref
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('BRL');
            $table->string('method', 30);            // credit_card, pix, qrcode, internal_balance
            $table->string('status', 30)->default('created');
            $table->string('failure_reason')->nullable();
            $table->string('simulation_rule')->nullable();
            $table->text('qr_code')->nullable();
            $table->string('qr_code_url')->nullable();
            $table->timestamp('processing_until')->nullable();
            $table->json('metadata')->nullable();

            // Card fields (if credit_card)
            $table->string('card_last4')->nullable();
            $table->string('card_brand')->nullable();

            // Customer fields
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();

            $table->string('description')->nullable();
            $table->string('idempotency_key')->nullable()->index();
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
            $table->index(['project_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
