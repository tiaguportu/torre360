<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodo_letivo', function (Blueprint $table) {
            $table->boolean('recuperacao_por_etapa')->default(false)->after('nota_recuperacao_minima');
            $table->boolean('exame_final_habilitado')->default(false)->after('recuperacao_por_etapa');
            $table->decimal('nota_aprovacao_pos_exame', 4, 2)->default(5.00)->after('exame_final_habilitado');
        });
    }

    public function down(): void
    {
        Schema::table('periodo_letivo', function (Blueprint $table) {
            $table->dropColumn(['recuperacao_por_etapa', 'exame_final_habilitado', 'nota_aprovacao_pos_exame']);
        });
    }
};
