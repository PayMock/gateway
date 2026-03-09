<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table("webhook_events", function (Blueprint $table) {
            $table->uuid("transaction_id")->nullable()->change();
            $table->string("payout_id", 40)->nullable()->after("transaction_id");
            $table->foreign("payout_id")->references("id")->on("payouts")->cascadeOnDelete();
            $table->index("payout_id");
        });
    }

    public function down(): void
    {
        Schema::table("webhook_events", function (Blueprint $table) {
            $table->dropColumn("payout_id");
            $table->uuid("transaction_id")->nullable(false)->change();
        });
    }
};
