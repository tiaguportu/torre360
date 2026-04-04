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
            try { DB::statement("ALTER TABLE matriculas ADD COLUMN situacao_matricula_id INTEGER"); } catch (\Exception $e) {}
        } else {
            Schema::table('matriculas', function (Blueprint $table) {
                if (!Schema::hasColumn('matriculas', 'situacao_matricula_id')) {
                    $table->foreignId('situacao_matricula_id')->nullable()->constrained('situacao_matriculas')->onDelete('set null');
                }
                if (Schema::hasColumn('matriculas', 'status')) {
                    $table->dropColumn('status');
                }
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
            Schema::table('matriculas', function (Blueprint $table) {
                if (Schema::hasColumn('matriculas', 'situacao_matricula_id')) {
                    $table->dropForeign(['situacao_matricula_id']);
                    $table->dropColumn('situacao_matricula_id');
                }
                if (!Schema::hasColumn('matriculas', 'status')) {
                    $table->string('status')->default('ativa');
                }
            });
        }
    }
};
