<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('scan_providers')
            ->where('provider_key', 'barcode_lookup')
            ->delete();
    }

    public function down(): void
    {
        // Barcode Lookup was intentionally removed from the provider stack.
    }
};
