<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conta_pagars', function (Blueprint $table) {
            $table->id();
            $table->string('descricao');
            $table->decimal('valor', 12, 2);
            $table->date('vencimento');
            $table->string('status')->default('pendente');
            $table->date('data_pagamento')->nullable();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedors')->nullOnDelete();
            $table->foreignId('plano_conta_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centro_custos')->nullOnDelete();
            $table->foreignId('transacao_bancaria_id')->nullable()->constrained('transacao_bancarias')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conta_pagars');
    }
};
