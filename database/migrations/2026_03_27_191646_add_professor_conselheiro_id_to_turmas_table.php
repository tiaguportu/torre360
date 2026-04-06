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
                DB::statement('ALTER TABLE turmas ADD COLUMN professor_conselheiro_id INTEGER');
            } catch (Exception $e) {
            }
        } else {
            Schema::table('turmas', function (Blueprint $table) {
                $table->foreignId('professor_conselheiro_id')->nullable()->constrained('pessoas')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            // No action in SQLite
        } else {
            Schema::table('turmas', function (Blueprint $table) {
                $table->dropForeign(['professor_conselheiro_id']);
                $table->dropColumn('professor_conselheiro_id');
            });
        }
    }
};
