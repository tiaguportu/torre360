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
@php
    $pai = null;
    $isResponsavelFinanceiro = false;
    if ($aluno) {
        $pai = $aluno->responsaveis->first(function ($resp) {
            return $resp->pivot && $resp->pivot->tipo_vinculo_id == 1; // 1 é Pai
        });
        if ($pai) {
            $isResponsavelFinanceiro = $responsaveis->contains(function ($rf) use ($pai) {
                return $rf->pessoa_id === $pai->id;
            });
        }
    }
@endphp

@if($pai)
<div style="margin-top: 50px; margin-bottom: 30px;">
_______________________________________________<br>
CONTRATANTE-ADERENTE: {{ $pai->nome }} - Pai{{ $isResponsavelFinanceiro ? ' e Responsável Financeiro' : '' }}<br><br>
CPF nº {{ $pai->cpf ?? '___________________________' }}
</div>
@endif
HTML;

        $maeHtml = <<<'HTML'
@php
    $mae = null;
    $isResponsavelFinanceiro = false;
    if ($aluno) {
        $mae = $aluno->responsaveis->first(function ($resp) {
            return $resp->pivot && $resp->pivot->tipo_vinculo_id == 2; // 2 é Mãe
        });
        if ($mae) {
            $isResponsavelFinanceiro = $responsaveis->contains(function ($rf) use ($mae) {
                return $rf->pessoa_id === $mae->id;
            });
        }
    }
@endphp

@if($mae)
<div style="margin-top: 50px; margin-bottom: 30px;">
_______________________________________________<br>
CONTRATANTE-ADERENTE: {{ $mae->nome }} - Mãe{{ $isResponsavelFinanceiro ? ' e Responsável Financeira' : '' }}<br><br>
CPF nº {{ $mae->cpf ?? '___________________________' }}
</div>
@endif
HTML;

        $respFinanceiroHtml = <<<'HTML'
@php
    $paiId = null;
    $maeId = null;
    if ($aluno) {
        $pai = $aluno->responsaveis->first(function ($resp) {
            return $resp->pivot && $resp->pivot->tipo_vinculo_id == 1;
        });
        $paiId = $pai ? $pai->id : null;

        $mae = $aluno->responsaveis->first(function ($resp) {
            return $resp->pivot && $resp->pivot->tipo_vinculo_id == 2;
        });
        $maeId = $mae ? $mae->id : null;
    }
@endphp

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

        // Atualiza ou insere as configurações de assinaturas de pais
        DB::table('configuracao')->updateOrInsert(
            ['campo' => 'template_contrato_assinatura_pai'],
            [
                'valor' => $paiHtml,
                'grupo' => 'Contrato',
                'ordem' => 0,
            ]
        );

        DB::table('configuracao')->updateOrInsert(
            ['campo' => 'template_contrato_assinatura_mae'],
            [
                'valor' => $maeHtml,
                'grupo' => 'Contrato',
                'ordem' => 0,
            ]
        );

        // Insere a nova configuração para responsável financeiro
        DB::table('configuracao')->updateOrInsert(
            ['campo' => 'template_contrato_assinatura_responsavel_financeiro'],
            [
                'valor' => $respFinanceiroHtml,
                'grupo' => 'Contrato',
                'ordem' => 0,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $paiHtmlOriginal = <<<'HTML'
@php
    $pai = null;
    if ($aluno) {
        $pai = $aluno->responsaveis->first(function ($resp) {
            return $resp->pivot && $resp->pivot->tipo_vinculo_id == 1; // 1 é Pai
        });
    }
@endphp

@if($pai)
<div style="margin-top: 50px; margin-bottom: 30px;">
_______________________________________________<br>
CONTRATANTE-ADERENTE: {{ $pai->nome }} - Pai<br><br>
CPF nº {{ $pai->cpf ?? '___________________________' }}
</div>
@endif
HTML;

        $maeHtmlOriginal = <<<'HTML'
@php
    $mae = null;
    if ($aluno) {
        $mae = $aluno->responsaveis->first(function ($resp) {
            return $resp->pivot && $resp->pivot->tipo_vinculo_id == 2; // 2 é Mãe
        });
    }
@endphp

@if($mae)
<div style="margin-top: 50px; margin-bottom: 30px;">
_______________________________________________<br>
CONTRATANTE-ADERENTE: {{ $mae->nome }} - Mãe<br><br>
CPF nº {{ $mae->cpf ?? '___________________________' }}
</div>
@endif
HTML;

        // Restaura as configurações originais
        DB::table('configuracao')
            ->where('campo', 'template_contrato_assinatura_pai')
            ->update(['valor' => $paiHtmlOriginal]);

        DB::table('configuracao')
            ->where('campo', 'template_contrato_assinatura_mae')
            ->update(['valor' => $maeHtmlOriginal]);

        // Remove a nova configuração
        DB::table('configuracao')
            ->where('campo', 'template_contrato_assinatura_responsavel_financeiro')
            ->delete();
    }
};
