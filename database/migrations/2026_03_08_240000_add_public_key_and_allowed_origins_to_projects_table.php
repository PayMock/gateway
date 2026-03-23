<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Public key for client-side use (pk_test_xxx) — safe to expose in frontend
            $table->string('public_key')->unique()->nullable()->after('api_key');

            // JSON array of allowed origin hosts for public API requests
            // Supports wildcards: *.domain.com, *.*.domain.com
            $table->json('allowed_origins')->nullable()->after('public_key');

            $table->index('public_key');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['public_key']);
            $table->dropColumn(['public_key', 'allowed_origins']);
        });
    }
};
