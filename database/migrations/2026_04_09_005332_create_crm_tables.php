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
        Schema::create('origem_interessado', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('status_interessado', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cor')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('tipo_contato_interessado', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('interessado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa');
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('origem_interessado_id')->constrained('origem_interessado');
            $table->foreignId('status_interessado_id')->constrained('status_interessado');
            $table->dateTime('data_proximo_contato')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('interessado_dependente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interessado_id')->constrained('interessado')->onDelete('cascade');
            $table->string('nome_crianca');
            $table->foreignId('serie_id')->constrained('serie');
            $table->date('data_nascimento')->nullable();
            $table->timestamps();
        });

        Schema::create('historico_contato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interessado_id')->constrained('interessado')->onDelete('cascade');
            $table->foreignId('tipo_contato_interessado_id')->constrained('tipo_contato_interessado');
            $table->text('relato');
            $table->dateTime('data_contato');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historico_contato');
        Schema::dropIfExists('interessado_dependente');
        Schema::dropIfExists('interessado');
        Schema::dropIfExists('tipo_contato_interessado');
        Schema::dropIfExists('status_interessado');
        Schema::dropIfExists('origem_interessado');
    }
};
