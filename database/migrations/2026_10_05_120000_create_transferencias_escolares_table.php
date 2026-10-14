<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias_escolares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('escola_externa_nome');
            $table->string('escola_externa_cidade')->nullable();
            $table->string('escola_externa_uf', 2)->nullable();
            $table->date('data');
            $table->text('motivo')->nullable();
            $table->string('status')->default('em_andamento');
            $table->boolean('historico_recebido')->default(false);
            $table->foreignId('solicitacao_documento_id')->nullable()->constrained('solicitacao_documentos')->nullOnDelete();
            $table->foreignId('historico_escolar_ano_id')->nullable()->constrained('historico_escolar_anos')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias_escolares');
    }
};
