<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoDesenvolvimentoDocentes\Schemas;

use App\Models\PlanoDesenvolvimentoDocente;
use App\Services\DesenvolvimentoDocenteService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PlanoDesenvolvimentoDocenteForm
{
    public static function configure(Schema $schema): Schema
    {
        $podeEditar = fn (?PlanoDesenvolvimentoDocente $record): bool => $record !== null && (bool) auth()->user()?->can('update', $record);

        return $schema
            ->components([
                Section::make('Plano')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('professor')
                            ->label('Professor')
                            ->content(fn (?PlanoDesenvolvimentoDocente $record): string => $record?->funcionario?->pessoa?->nome ?? '—'),
                        Placeholder::make('avaliacao')
                            ->label('Avaliação de origem')
                            ->content(fn (?PlanoDesenvolvimentoDocente $record): string => $record?->avaliacao
                                ? ($record->avaliacao->ciclo?->nome ?? '—').' · '.$record->avaliacao->data_observacao->format('d/m/Y').' · '.number_format((float) $record->avaliacao->pontuacao_percentual, 1, ',', '.').'%'
                                : '—'),
                        Placeholder::make('situacao')
                            ->label('Situação')
                            ->content(fn (?PlanoDesenvolvimentoDocente $record): string => $record?->status?->getLabel() ?? '—'),
                        Placeholder::make('ciencia')
                            ->label('Ciência do professor')
                            ->content(fn (?PlanoDesenvolvimentoDocente $record): string => $record?->ciencia_em ? $record->ciencia_em->format('d/m/Y H:i') : 'Ainda não deu ciência'),
                        Textarea::make('objetivo')
                            ->label('Objetivo do plano')
                            ->helperText('O que se espera que mude até a revisão. Guardado cifrado.')
                            ->rows(4)
                            ->maxLength(5000)
                            ->disabled(fn (?PlanoDesenvolvimentoDocente $record): bool => ! $podeEditar($record))
                            ->columnSpanFull(),
                        DatePicker::make('data_revisao')
                            ->label('Data de revisão')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->disabled(fn (?PlanoDesenvolvimentoDocente $record): bool => ! $podeEditar($record)),
                    ]),
                Section::make('Autoavaliação × observações do ciclo')
                    ->description('Notas dadas pelo professor a si mesmo (se já enviou a autoavaliação) e média das observações finalizadas da coordenação. Diferença positiva: o professor se vê melhor do que foi visto.')
                    ->collapsible()
                    ->schema([
                        Placeholder::make('comparativo')
                            ->hiddenLabel()
                            ->content(fn (?PlanoDesenvolvimentoDocente $record): HtmlString => self::tabelaComparativa($record)),
                    ]),
            ]);
    }

    private static function tabelaComparativa(?PlanoDesenvolvimentoDocente $plano): HtmlString
    {
        $ciclo = $plano?->avaliacao?->ciclo;

        if (! $plano || ! $ciclo) {
            return new HtmlString('<span>—</span>');
        }

        $dados = app(DesenvolvimentoDocenteService::class)->comparativo($plano->funcionario, $ciclo);
        $formato = fn (null|int|float $v): string => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');

        $linhas = '';
        foreach ($dados['linhas'] as $linha) {
            $diferenca = $linha['diferenca'];
            $cor = $diferenca === null ? '' : ($diferenca > 0 ? 'text-warning-600' : ($diferenca < 0 ? 'text-info-600' : ''));
            $linhas .= '<tr class="border-b border-gray-100 dark:border-white/5"><td class="py-2 pr-3">'.e($linha['nome']).'</td>'
                .'<td class="pr-3 text-right tabular-nums">'.$formato($linha['auto']).'</td>'
                .'<td class="pr-3 text-right tabular-nums">'.$formato($linha['observada']).'</td>'
                .'<td class="text-right tabular-nums '.$cor.'">'.($diferenca === null ? '—' : ($diferenca > 0 ? '+' : '').$formato($diferenca)).'</td></tr>';
        }

        $rodape = sprintf(
            '<p class="mt-2 text-xs text-gray-500">Escala de 1 a %d. Pontuação: autoavaliação %s · observações %s (%d finalizada(s)).</p>',
            $dados['escala'],
            $dados['auto_pontuacao'] !== null ? number_format($dados['auto_pontuacao'], 1, ',', '.').'%' : 'não enviada',
            $dados['observada_pontuacao'] !== null ? number_format($dados['observada_pontuacao'], 1, ',', '.').'%' : '—',
            $dados['avaliacoes'],
        );

        return new HtmlString(
            '<table class="w-full text-left text-sm"><thead><tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">'
            .'<th class="py-2 pr-3">Critério</th><th class="pr-3 text-right">Autoavaliação</th><th class="pr-3 text-right">Observações (média)</th><th class="text-right">Diferença</th>'
            .'</tr></thead><tbody>'.$linhas.'</tbody></table>'.$rodape,
        );
    }
}
