<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('public_id')->unique(); // evt_xxx
            $table->string('event_type', 60); // payment.approved, payment.failed, etc.
            $table->jsonb('payload');
            $table->string('status', 20)->default('pending'); // pending, delivered, failed
            $table->integer('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index('next_attempt_at');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
