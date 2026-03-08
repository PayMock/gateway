<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('transaction_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained()->cascadeOnDelete();
            $table->integer('attempt_number');
            $table->string('status', 30);
            $table->string('rule_triggered')->nullable();
            $table->string('reason')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_attempts');
    }
};
