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
            // No SQLite antigo, mudar nullabilidade exige recriação de tabela.
            // Ignoramos a mudança física para evitar erro pragma_table_xinfo.
        } else {
            Schema::table('avaliacao', function (Blueprint $table) {
                $table->date('data_ocorrencia')->nullable()->change();
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
                $table->date('data_ocorrencia')->nullable(false)->change();
            });
        }
    }
};
