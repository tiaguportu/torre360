<?php

use App\Services\Painel\Indicadores\AlunosEmRiscoDeEvasao;
use App\Services\Painel\Indicadores\CaixaProjetado30Dias;
use App\Services\Painel\Indicadores\ConversaoDeLeads90Dias;
use App\Services\Painel\Indicadores\DespesasNoOrcamento;
use App\Services\Painel\Indicadores\FrequenciaMedia30Dias;
use App\Services\Painel\Indicadores\InadimplenciaEmAberto;
use App\Services\Painel\Indicadores\LeadsNovos30Dias;
use App\Services\Painel\Indicadores\MargemDasTurmas;
use App\Services\Painel\Indicadores\MatriculasAtivas;
use App\Services\Painel\Indicadores\OcupacaoDasTurmas;
use App\Services\Painel\Indicadores\ResultadoDoMesAnterior;
use App\Services\Painel\Indicadores\TaxaDeInadimplencia;
use App\Services\Painel\Indicadores\VagasLivres;

return [
    /*
    |--------------------------------------------------------------------------
    | Indicadores do Painel da Direção (Onda 45)
    |--------------------------------------------------------------------------
    |
    | Cada classe implementa App\Contracts\IndicadorDoPainel e tem UMA definição do número. Um módulo novo que queira aparecer no
    | painel acrescenta a sua classe aqui; cada pessoa vê só os indicadores cuja permissão (a da tela onde o número nasce) ela tem.
    | A ordem dos grupos no painel é a da primeira vez que aparecem nesta lista.
    */
    'indicadores' => [
        MatriculasAtivas::class,
        OcupacaoDasTurmas::class,
        VagasLivres::class,
        AlunosEmRiscoDeEvasao::class,
        InadimplenciaEmAberto::class,
        TaxaDeInadimplencia::class,
        ResultadoDoMesAnterior::class,
        CaixaProjetado30Dias::class,
        MargemDasTurmas::class,
        DespesasNoOrcamento::class,
        LeadsNovos30Dias::class,
        ConversaoDeLeads90Dias::class,
        FrequenciaMedia30Dias::class,
    ],

    // Por quanto tempo (segundos) o painel guarda o valor calculado de cada indicador. O botão "Atualizar" esquece tudo.
    'cache_segundos' => (int) env('PAINEL_CACHE_SEGUNDOS', 600),

    // A variação do cartão compara com a fotografia de N dias atrás (a mais recente que não seja posterior a essa data).
    'comparacao_dias' => 30,

    // Dias de fotografias que a série do cartão mostra.
    'serie_dias' => 90,

    // Fotografias mais antigas que isto (dias) são apagadas pela rotina de limpeza. Passou de dois anos, o ano anterior já foi comparado.
    'retencao_dias' => 800,
];
