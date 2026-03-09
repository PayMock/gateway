<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table("balances", function (Blueprint $table) {
            if (!Schema::hasColumn("balances", "withdrawn")) {
                $table->decimal("withdrawn", 14, 2)->default(0)->after("pending");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("balances", function (Blueprint $table) {
            if (Schema::hasColumn("balances", "withdrawn")) {
                $table->dropColumn("withdrawn");
            }
        });
    }
};
