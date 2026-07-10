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
@php
    $pai = null;
    $mae = null;
    $paiId = null;
    $maeId = null;
    $paiResponsavel = false;
    $maeResponsavel = false;

    if ($aluno) {
        // Encontra Pai
        $pai = $aluno->responsaveis->first(function ($resp) {
            return $resp->pivot && $resp->pivot->tipo_vinculo_id == 1;
        });
        if ($pai) {
            $paiId = $pai->id;
            $paiResponsavel = $responsaveis->contains(function ($rf) use ($pai) {
                return $rf->pessoa_id === $pai->id;
            });
        }

        // Encontra Mãe
        $mae = $aluno->responsaveis->first(function ($resp) {
            return $resp->pivot && $resp->pivot->tipo_vinculo_id == 2;
        });
        if ($mae) {
            $maeId = $mae->id;
            $maeResponsavel = $responsaveis->contains(function ($rf) use ($mae) {
                return $rf->pessoa_id === $mae->id;
            });
        }
    }
@endphp

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
