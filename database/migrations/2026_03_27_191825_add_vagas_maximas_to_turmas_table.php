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
            try { DB::statement("ALTER TABLE turmas ADD COLUMN vagas_maximas INTEGER DEFAULT 30"); } catch (\Exception $e) {}
        } else {
            Schema::table('turmas', function (Blueprint $table) {
                $table->integer('vagas_maximas')->default(30);
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
            Schema::table('turmas', function (Blueprint $table) {
                $table->dropColumn('vagas_maximas');
            });
        }
    }
};
