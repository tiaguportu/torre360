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
        Schema::create('transacao_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banco_id')->constrained('bancos')->onDelete('cascade');
            $table->foreignId('fatura_id')->nullable()->constrained('faturas')->onDelete('set null');
            $table->foreignId('plano_conta_id')->nullable()->constrained('plano_contas')->onDelete('set null');
            $table->foreignId('centro_custo_id')->nullable()->constrained('centro_custos')->onDelete('set null');
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedors')->onDelete('set null');

            $table->enum('tipo', ['entrada', 'saida']);
            $table->decimal('valor', 15, 2);
            $table->date('data_transacao');
            $table->string('descricao')->nullable();
            $table->boolean('conciliado')->default(false);
            $table->string('external_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transacao_bancarias');
    }
};
