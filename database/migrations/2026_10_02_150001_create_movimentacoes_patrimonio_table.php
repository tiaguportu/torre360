<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes_patrimonio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bem_patrimonial_id')->constrained('bens_patrimoniais')->cascadeOnDelete();
            $table->string('tipo');
            $table->foreignId('unidade_anterior_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->foreignId('unidade_nova_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->foreignId('sala_anterior_id')->nullable()->constrained('sala')->nullOnDelete();
            $table->foreignId('sala_nova_id')->nullable()->constrained('sala')->nullOnDelete();
            $table->string('status_anterior')->nullable();
            $table->string('status_novo')->nullable();
            $table->date('data');
            $table->text('observacao')->nullable();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_patrimonio');
    }
};
