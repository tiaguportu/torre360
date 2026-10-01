<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            // pix_copia_e_cola NÃO entra aqui: já existe nesta tabela, herdada da antiga
            // `titulos` (renomeada para `faturas` em 2026_04_08_092749). Só passa a ser
            // preenchida de verdade a partir desta onda, via GatewayPagamento::criarCobranca().
            $table->string('gateway')->nullable()->after('status');
            $table->string('gateway_id')->nullable()->unique()->after('gateway');
            $table->string('status_gateway')->nullable()->after('gateway_id');
            $table->string('linha_digitavel')->nullable()->after('status_gateway');
            $table->string('boleto_url')->nullable()->after('linha_digitavel');
            $table->string('link_pagamento')->nullable()->after('boleto_url');
        });
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropColumn([
                'gateway',
                'gateway_id',
                'status_gateway',
                'linha_digitavel',
                'boleto_url',
                'link_pagamento',
            ]);
        });
    }
};
