<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = DB::select('SHOW TABLES');
        exit(print_r($tables, true));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
