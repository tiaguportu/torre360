<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista de material escolar por série e período letivo (Onda 59). Uma lista por série e período; os itens têm a
 * confirmação de uso individual quando a descrição se parece com material de uso coletivo (Lei nº 9.870/1999, art. 1º,
 * § 7º, incluído pela Lei nº 12.886/2013).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listas_material_escolar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serie_id')->constrained('serie')->restrictOnDelete();
            $table->foreignId('periodo_letivo_id')->constrained('periodo_letivo')->restrictOnDelete();
            $table->string('status', 20)->default('rascunho');
            $table->text('observacao')->nullable();
            $table->timestamp('publicada_em')->nullable();
            $table->foreignId('publicada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('criada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['serie_id', 'periodo_letivo_id'], 'uq_lista_material_serie_periodo');
            $table->index('status', 'idx_lista_material_status');
        });

        Schema::create('itens_lista_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_material_id')->constrained('listas_material_escolar')->cascadeOnDelete();
            $table->string('descricao', 160);
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->string('unidade', 30)->nullable();
            $table->string('categoria', 30)->default('papelaria');
            $table->string('observacao', 255)->nullable();
            $table->boolean('uso_individual_confirmado')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['lista_material_id', 'ordem'], 'idx_item_lista_ordem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_lista_material');
        Schema::dropIfExists('listas_material_escolar');
    }
};
