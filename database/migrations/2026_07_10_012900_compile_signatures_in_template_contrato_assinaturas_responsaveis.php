<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $compiladoHtml = <<<'HTML'
{{-- 1. Assinatura do Pai --}}
@if($pai)
<div style="margin-top: 50px; margin-bottom: 30px;">
_______________________________________________<br>
CONTRATANTE-ADERENTE: {{ $pai->nome }} - Pai{{ $paiResponsavel ? ' e Responsável Financeiro' : '' }}<br><br>
CPF nº {{ $pai->cpf ?? '___________________________' }}
</div>
@endif

{{-- 2. Assinatura da Mãe --}}
@if($mae)
<div style="margin-top: 50px; margin-bottom: 30px;">
_______________________________________________<br>
CONTRATANTE-ADERENTE: {{ $mae->nome }} - Mãe{{ $maeResponsavel ? ' e Responsável Financeira' : '' }}<br><br>
CPF nº {{ $mae->cpf ?? '___________________________' }}
</div>
@endif

{{-- 3. Assinatura de Terceiros que sejam Responsáveis Financeiros --}}
@foreach($responsaveis as $rf)
    @if($rf->pessoa && $rf->pessoa_id !== $paiId && $rf->pessoa_id !== $maeId)
    <div style="margin-top: 50px; margin-bottom: 30px;">
    _______________________________________________<br>
    CONTRATANTE-ADERENTE: {{ $rf->pessoa->nome }} - Responsável Financeiro<br><br>
    CPF nº {{ $rf->pessoa->cpf ?? '___________________________' }}
    </div>
    @endif
@endforeach
HTML;

        DB::table('configuracao')
            ->where('campo', 'template_contrato_assinaturas_responsaveis')
            ->update(['valor' => $compiladoHtml]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $originalHtml = <<<'HTML'
@foreach($responsaveis as $rf)
    @if($rf->pessoa)
    <div style="margin-top: 50px; margin-bottom: 30px;">
    _______________________________________________<br>
    CONTRATANTE-ADERENTE: {{ $rf->pessoa->nome }}<br><br>
    CPF nº {{ $rf->pessoa->cpf ?? '___________________________' }}
    </div>
    @endif
@endforeach
HTML;

        DB::table('configuracao')
            ->where('campo', 'template_contrato_assinaturas_responsaveis')
            ->update(['valor' => $originalHtml]);
    }
};
