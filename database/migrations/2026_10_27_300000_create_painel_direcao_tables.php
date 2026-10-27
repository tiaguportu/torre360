<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Meta anual de um indicador. `unidade_id` 0 é a escola toda (não é chave estrangeira: a unique precisa de um valor, não de NULL).
        Schema::create('indicador_metas', function (Blueprint $table): void {
            $table->id();
            $table->string('indicador', 60);
            $table->unsignedSmallInteger('ano');
            $table->unsignedBigInteger('unidade_id')->default(0);
            $table->decimal('meta', 14, 2);
            // Faixa amarela do semáforo: quanto o valor pode ficar pior que a meta, em % da meta, antes de ficar vermelho.
            $table->decimal('tolerancia_percentual', 5, 2)->default(10);
            $table->string('observacao', 255)->nullable();
            $table->timestamps();

            $table->unique(['indicador', 'ano', 'unidade_id'], 'indicador_metas_unica');
        });

        // Fotografia diária de cada indicador, para séries históricas baratas e comparação com o período anterior.
        Schema::create('painel_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('data');
            $table->string('indicador', 60);
            $table->unsignedBigInteger('unidade_id')->default(0);
            $table->decimal('valor', 16, 4)->nullable();
            $table->timestamps();

            $table->unique(['data', 'indicador', 'unidade_id'], 'painel_snapshots_unica');
            $table->index(['indicador', 'unidade_id', 'data'], 'painel_snapshots_serie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('painel_snapshots');
        Schema::dropIfExists('indicador_metas');
    }
};
