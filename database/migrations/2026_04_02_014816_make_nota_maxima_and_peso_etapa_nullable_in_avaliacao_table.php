<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            // Ignoramos o .change() no SQLite antigo para evitar o erro pragma_table_xinfo.
        } else {
            Schema::table('avaliacao', function (Blueprint $table) {
                $table->decimal('nota_maxima', 5, 2)->nullable()->change();
                $table->decimal('peso_etapa_avaliativa', 5, 2)->nullable()->change();
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
                $table->decimal('nota_maxima', 5, 2)->nullable(false)->change();
                $table->decimal('peso_etapa_avaliativa', 5, 2)->nullable(false)->change();
            });
        }
    }
};
