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
        Schema::create('aluno_responsavel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('responsavel_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('tipo_vinculo_id')->nullable()->constrained('tipo_vinculos')->nullOnDelete();
            $table->boolean('permissao_retirada')->default(false);
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aluno_responsavel');
    }
};
