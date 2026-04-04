<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            try { DB::statement("ALTER TABLE cronograma_aula ADD COLUMN periodo_letivo_id INTEGER"); } catch (\Exception $e) {}
        } else {
            Schema::table('cronograma_aula', function (Blueprint $table) {
                $table->foreignId('periodo_letivo_id')->nullable()->constrained('periodo_letivo')->onDelete('cascade');
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
            Schema::table('cronograma_aula', function (Blueprint $table) {
                $table->dropForeign(['periodo_letivo_id']);
                $table->dropColumn('periodo_letivo_id');
            });
        }
    }
};
