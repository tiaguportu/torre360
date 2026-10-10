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
        $paiHtml = <<<'HTML'
@if($pai)
<div style="margin-top: 50px; margin-bottom: 30px;">
_______________________________________________<br>
CONTRATANTE-ADERENTE: {{ $pai->nome }} - Pai{{ $isResponsavelFinanceiro ? ' e Responsável Financeiro' : '' }}<br><br>
CPF nº {{ $pai->cpf ?? '___________________________' }}
</div>
@endif
HTML;

        $maeHtml = <<<'HTML'
@if($mae)
<div style="margin-top: 50px; margin-bottom: 30px;">
_______________________________________________<br>
CONTRATANTE-ADERENTE: {{ $mae->nome }} - Mãe{{ $isResponsavelFinanceiro ? ' e Responsável Financeira' : '' }}<br><br>
CPF nº {{ $mae->cpf ?? '___________________________' }}
</div>
@endif
HTML;

        $respFinanceiroHtml = <<<'HTML'
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
            ->where('campo', 'template_contrato_assinatura_pai')
            ->update(['valor' => $paiHtml]);

        DB::table('configuracao')
            ->where('campo', 'template_contrato_assinatura_mae')
            ->update(['valor' => $maeHtml]);

        DB::table('configuracao')
            ->where('campo', 'template_contrato_assinatura_responsavel_financeiro')
            ->update(['valor' => $respFinanceiroHtml]);

        DB::table('configuracao')
            ->where('campo', 'template_contrato_assinaturas_responsaveis')
            ->update(['valor' => $compiladoHtml]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversão não é necessária para manter os templates limpos
    }
};
