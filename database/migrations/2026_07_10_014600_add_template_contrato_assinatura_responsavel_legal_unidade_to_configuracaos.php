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
        $html = <<<'HTML'
@if($unidade && !$unidade->representantesLegais->isEmpty())
    @foreach($unidade->representantesLegais as $rep)
        <div style="margin-top: 50px; margin-bottom: 30px;">
        _______________________________________________<br>
        CONTRATADA: {{ $rep->nome }} - {{ $rep->pivot->cargo ?? 'Representante Legal' }}<br><br>
        CPF nº {{ $rep->cpf ?? '___________________________' }}
        </div>
    @endforeach
@else
    <div style="margin-top: 50px; margin-bottom: 30px;">
    _______________________________________________<br>
    CONTRATADA: Escola Torre de Marfim - Representante Legal<br><br>
    CPF nº ___________________________
    </div>
@endif
HTML;

        DB::table('configuracao')->updateOrInsert(
            ['campo' => 'template_contrato_assinatura_responsavel_legal_unidade'],
            [
                'valor' => $html,
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
        DB::table('configuracao')
            ->where('campo', 'template_contrato_assinatura_responsavel_legal_unidade')
            ->delete();
    }
};
