<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->uuid('project_id')->primary();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->decimal('available', 14, 2)->default(0);
            $table->decimal('pending', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
