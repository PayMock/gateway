<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('public_id')->unique();   // chg_xxx
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('BRL');
            $table->string('description')->nullable();

            // Customer info captured at charge creation
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();

            // pending → awaiting payment
            // paid    → payment confirmed
            // expired → charge expired before payment
            // canceled → manually canceled
            $table->string('status', 30)->default('pending');

            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
