<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->uuid('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();

            $table->enum('type', ['credit', 'debit']);
            $table->enum('balance_type', ['pending', 'available']);
            $table->decimal('amount', 14, 2);
            $table->string('description')->nullable();

            $table->string('source_type')->nullable(); // payment, payout, advance, fee
            $table->string('source_id', 40)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_transactions');
    }
};
