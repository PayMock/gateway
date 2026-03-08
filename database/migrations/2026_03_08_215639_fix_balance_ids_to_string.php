<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Robust approach for PostgreSQL: USING id::text
        DB::statement('ALTER TABLE balance_transactions ALTER COLUMN id TYPE VARCHAR(40) USING id::text');
        DB::statement('ALTER TABLE balance_transactions ALTER COLUMN source_id TYPE VARCHAR(40) USING source_id::text');

        DB::statement('ALTER TABLE payouts ALTER COLUMN id TYPE VARCHAR(40) USING id::text');
    }

    public function down(): void
    {
        // No easy way back automatically without potentially losing data,
        // but btxn_ IDs won't fit back into UUID anyway.
    }
};
