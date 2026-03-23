<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Links a transaction to the charge it was created from.
            // Null for direct API payments (POST /v1/payments).
            $table->foreignUuid('charge_id')
                ->nullable()
                ->after('project_id')
                ->constrained('charges')
                ->nullOnDelete();

            $table->index('charge_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['charge_id']);
            $table->dropIndex(['charge_id']);
            $table->dropColumn('charge_id');
        });
    }
};
