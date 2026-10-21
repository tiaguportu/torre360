<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes\Schemas;

use App\Enums\ArredondamentoReajuste;
use App\Models\CenarioReajuste;
use App\Models\PeriodoRematricula;
use App\Models\PlanilhaLeiMensalidade;
use App\Services\ReajusteMensalidadeService;
use App\Support\Formatacao;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CenarioReajusteForm
{
    public static function configure(Schema $schema): Schema
    {
        $somenteSimulacao = fn (?CenarioReajuste $record): bool => $record !== null && ! auth()->user()?->can('update', $record);

        return $schema
            ->components([
                Section::make('Cenário')
                    ->columns(2)
                    ->schema([
                        Select::make('periodo_rematricula_id')
                            ->label('Campanha de rematrícula')
                            ->options(fn (): array => PeriodoRematricula::query()->orderByDesc('data_inicio')->pluck('nome', 'id')->all())
                            ->searchable()
                            ->required()
                            ->disabledOn(['edit', 'view'])
                            ->helperText('O valor atual de cada série vem dos contratos das matrículas ativas do período de origem da campanha.'),
                        TextInput::make('nome')
                            ->label('Nome do cenário')
                            ->placeholder('Ex.: Reajuste 2027 — 6,5%')
                            ->required()
                            ->maxLength(255)
                            ->disabled($somenteSimulacao),
                        TextInput::make('percentual_padrao')
                            ->label('Percentual de reajuste')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(ReajusteMensalidadeService::PERCENTUAL_MINIMO)
                            ->maxValue(ReajusteMensalidadeService::PERCENTUAL_MAXIMO)
                            ->suffix('%')
                            ->required()
                            ->disabled($somenteSimulacao)
                            ->helperText('Vale para todas as séries que não tiverem percentual próprio.'),
                        Select::make('arredondamento')
                            ->label('Arredondamento do valor novo')
                            ->options(ArredondamentoReajuste::class)
                            ->default(ArredondamentoReajuste::Centavos->value)
                            ->required()
                            ->disabled($somenteSimulacao),
                        Select::make('planilha_lei_id')
                            ->label('Planilha de custos (Lei das Mensalidades)')
                            ->options(fn (): array => PlanilhaLeiMensalidade::query()->orderByDesc('ano_letivo_destino')->get()
                                ->mapWithKeys(fn (PlanilhaLeiMensalidade $p): array => [$p->id => "{$p->titulo} ({$p->ano_letivo_destino})"])->all())
                            ->searchable()
                            ->disabled($somenteSimulacao)
                            ->helperText('Ajuda a conferir o percentual com o que foi calculado e divulgado às famílias.'),
                        Textarea::make('observacao')
                            ->label('Observação')
                            ->rows(2)
                            ->maxLength(2000)
                            ->disabled($somenteSimulacao)
                            ->columnSpanFull(),
                    ]),
                Section::make('Projeção e pontos de atenção')
                    ->visibleOn(['edit', 'view'])
                    ->schema([
                        Placeholder::make('resumo')
                            ->hiddenLabel()
                            ->content(fn (?CenarioReajuste $record): HtmlString => self::resumo($record)),
                    ]),
            ]);
    }

    private static function resumo(?CenarioReajuste $cenario): HtmlString
    {
        if (! $cenario) {
            return new HtmlString('<span>—</span>');
        }

        $servico = app(ReajusteMensalidadeService::class);
        $p = $servico->projecao($cenario);
        $alertas = $servico->alertas($cenario);

        $html = '<div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">'
            .'<div><div class="text-gray-500">Receita anual atual</div><strong>'.e(Formatacao::reais($p['receita_atual'])).'</strong></div>'
            .'<div><div class="text-gray-500">Receita anual com reajuste</div><strong>'.e(Formatacao::reais($p['receita_nova'])).'</strong></div>'
            .'<div><div class="text-gray-500">Diferença</div><strong>'.e(Formatacao::reais($p['diferenca'])).' ('.e(number_format($p['variacao_percentual'], 2, ',', '.')).'%)</strong></div>'
            .'<div><div class="text-gray-500">Líquida de bolsas (estimada)</div><strong>'.e(Formatacao::reais($p['receita_liquida_nova'])).'</strong></div>'
            .'</div><p class="mt-2 text-xs text-gray-500">Base: '.$p['alunos'].' aluno(s) com contrato no período de origem. Não considera inadimplência, evasão nem alunos novos.</p>';

        if ($cenario->comunicado_em) {
            $html .= '<p class="mt-2 text-sm">Comunicado às famílias registrado em '.e($cenario->comunicado_em->format('d/m/Y H:i')).'.</p>';
        }

        foreach ($alertas as $alerta) {
            $html .= '<p class="mt-2 text-sm text-warning-700">⚠️ '.e($alerta).'</p>';
        }

        return new HtmlString($html);
    }
}
