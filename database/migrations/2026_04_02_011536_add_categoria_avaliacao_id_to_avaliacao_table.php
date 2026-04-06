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
                DB::statement('ALTER TABLE avaliacao ADD COLUMN categoria_avaliacao_id INTEGER');
            } catch (Exception $e) {
            }
        } else {
            Schema::table('avaliacao', function (Blueprint $table) {
                $table->foreignId('categoria_avaliacao_id')->nullable()->constrained('categoria_avaliacao');
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
            Schema::table('avaliacao', function (Blueprint $table) {
                $table->dropConstrainedForeignId('categoria_avaliacao_id');
            });
        }
    }
};
