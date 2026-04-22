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
        Schema::table('relatorio_preceptoria', function (Blueprint $table) {
            // 1. Remover a FK primeiro
            $table->dropForeign(['preceptoria_id']);
            // 2. Remover o índice único
            $table->dropUnique(['preceptoria_id']);
            // 3. Adicionar a FK novamente (sem o unique)
            $table->foreign('preceptoria_id')
                ->references('id')
                ->on('preceptoria')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('relatorio_preceptoria', function (Blueprint $table) {
            $table->dropForeign(['preceptoria_id']);
            $table->unique('preceptoria_id');
            $table->foreign('preceptoria_id')
                ->references('id')
                ->on('preceptoria')
                ->onDelete('cascade');
        });
    }
};
