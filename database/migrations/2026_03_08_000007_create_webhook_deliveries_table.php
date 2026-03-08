<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')
                ->references('id')
                ->on('webhook_events')
                ->cascadeOnDelete();
            $table->integer('attempt_number');
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->boolean('is_success')->default(false);
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
