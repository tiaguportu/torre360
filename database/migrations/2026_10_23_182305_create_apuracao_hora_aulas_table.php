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
        Schema::create('apuracao_hora_aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->integer('ano');
            $table->integer('mes'); // 1 a 12
            $table->integer('total_aulas_previstas')->default(0);
            $table->integer('total_aulas_ministradas')->default(0);
            $table->integer('total_aulas_reposicao')->default(0);
            $table->integer('total_aulas_faltadas')->default(0);
            $table->decimal('total_horas_atividade', 5, 2)->default(0);
            $table->decimal('valor_hora_aula', 10, 2)->default(0);
            $table->decimal('percentual_dsr', 5, 2)->default(16.67); // 1/6 CLT
            $table->decimal('valor_total_bruto', 10, 2)->default(0);
            $table->string('status', 30)->default('aberto'); // aberto, conferido, homologado, contestado
            $table->dateTime('conferido_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pessoa_id', 'ano', 'mes'], 'apuracao_hora_aula_unica_prof_mes_ano');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apuracao_hora_aulas');
    }
};
