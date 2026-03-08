<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('public_id')->unique();  // proj_xxx
            $table->string('api_key')->unique();     // sk_test_xxx
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('api_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
