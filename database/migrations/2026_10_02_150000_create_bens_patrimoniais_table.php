<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bens_patrimoniais', function (Blueprint $table) {
            $table->id();
            $table->string('descricao');
            $table->string('numero_patrimonio')->nullable()->unique();
            $table->string('categoria');
            $table->date('data_aquisicao')->nullable();
            $table->decimal('valor_aquisicao', 10, 2)->nullable();
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->foreignId('sala_id')->nullable()->constrained('sala')->nullOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedors')->nullOnDelete();
            $table->string('status')->default('em_uso');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bens_patrimoniais');
    }
};
