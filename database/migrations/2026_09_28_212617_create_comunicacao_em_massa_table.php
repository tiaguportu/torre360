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
        Schema::create('comunicacao_em_massa', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo_publico');
            $table->json('filtros')->nullable();
            $table->string('canal')->default('email');
            $table->string('assunto');
            $table->longText('corpo');
            $table->string('status')->default('rascunho');
            $table->unsignedInteger('total_destinatarios')->nullable();
            $table->unsignedInteger('total_enviados')->default(0);
            $table->unsignedInteger('total_falhas')->default(0);
            $table->foreignId('enviado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('enviado_em')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comunicacao_em_massa');
    }
};
