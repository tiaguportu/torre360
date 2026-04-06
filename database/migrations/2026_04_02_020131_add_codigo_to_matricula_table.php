<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement('ALTER TABLE matricula ADD COLUMN codigo VARCHAR');
            } catch (Exception $e) {
            }
        } else {
            Schema::table('matricula', function (Blueprint $table) {
                $table->string('codigo')->nullable()->unique()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            // No action
        } else {
            Schema::table('matricula', function (Blueprint $table) {
                $table->dropColumn('codigo');
            });
        }
    }
};
