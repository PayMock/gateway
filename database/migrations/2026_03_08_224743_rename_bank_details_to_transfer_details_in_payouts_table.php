<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("payouts", function (Blueprint $table) {
            if (Schema::hasColumn("payouts", "bank_details") && !Schema::hasColumn("payouts", "transfer_details")) {
                $table->renameColumn("bank_details", "transfer_details");
            }
        });
    }

    public function down(): void
    {
        Schema::table("payouts", function (Blueprint $table) {
            if (Schema::hasColumn("payouts", "transfer_details") && !Schema::hasColumn("payouts", "bank_details")) {
                $table->renameColumn("transfer_details", "bank_details");
            }
        });
    }
};
