<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 86 (sem a distribuição da Onda 85): o consultor publica os horários em que recebe visitas e a família escolhe um numa
 * página pública. A visita guarda de onde veio, em que unidade, o token dos links de reagendar e cancelar e se a família já
 * recebeu o lembrete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilidade_visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            // 1 (segunda) a 7 (domingo), como o `dayOfWeekIso` do Carbon.
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->unsignedSmallInteger('duracao_minutos')->default(30);
            $table->unsignedSmallInteger('antecedencia_minima_horas')->default(24);
            $table->unsignedSmallInteger('antecedencia_maxima_dias')->default(30);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'dia_semana']);
        });

        Schema::table('visita_interessado', function (Blueprint $table) {
            $table->string('token_agendamento', 64)->nullable()->unique()->after('observacoes');
            $table->string('origem', 20)->default('interna')->after('token_agendamento');
            $table->foreignId('unidade_id')->nullable()->after('origem')->constrained('unidade')->nullOnDelete();
            $table->dateTime('lembrete_familia_enviado_em')->nullable()->after('unidade_id');
        });
    }

    public function down(): void
    {
        Schema::table('visita_interessado', function (Blueprint $table) {
            $table->dropForeign(['unidade_id']);
            $table->dropUnique(['token_agendamento']);
            $table->dropColumn(['token_agendamento', 'origem', 'unidade_id', 'lembrete_familia_enviado_em']);
        });

        Schema::dropIfExists('disponibilidade_visitas');
    }
};
