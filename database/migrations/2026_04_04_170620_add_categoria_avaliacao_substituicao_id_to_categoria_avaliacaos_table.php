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
        Schema::table('categoria_avaliacao', function (Blueprint $table) {
            $table->foreignId('categoria_avaliacao_substituicao_id')
                ->nullable()
                ->constrained('categoria_avaliacao')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categoria_avaliacao', function (Blueprint $table) {
            $table->dropForeign(['categoria_avaliacao_substituicao_id']);
            $table->dropColumn('categoria_avaliacao_substituicao_id');
        });
    }
};
