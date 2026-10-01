<?php

namespace App\Filament\Resources\DiaNaoLetivos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\DiaNaoLetivos\DiaNaoLetivoResource;
use App\Models\DiaNaoLetivo;
use App\Models\PeriodoLetivo;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListDiaNaoLetivos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = DiaNaoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerarFDSFeriados')
                ->label('Gerar FDS e Feriados')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->form([
                    Select::make('periodo_letivo_id')
                        ->label('Período Letivo')
                        ->options(PeriodoLetivo::all()->pluck('nome', 'id'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $periodo = PeriodoLetivo::findOrFail($data['periodo_letivo_id']);
                    $start = Carbon::parse($periodo->data_inicio);
                    $end = Carbon::parse($periodo->data_fim);
                    $count = 0;

                    $current = $start->copy();
                    while ($current <= $end) {
                        $descricao = null;
                        if ($current->isSaturday()) {
                            $descricao = 'Sábado';
                        } elseif ($current->isSunday()) {
                            $descricao = 'Domingo';
                        } else {
                            $feriado = DiaNaoLetivo::getFeriadoNacional($current);
                            if ($feriado) {
                                $descricao = $feriado;
                            }
                        }

                        if ($descricao) {
                            DiaNaoLetivo::updateOrCreate(
                                [
                                    'periodo_letivo_id' => $periodo->id,
                                    'data' => $current->toDateString(),
                                ],
                                [
                                    'descricao' => $descricao,
                                    'flag_ativo' => true,
                                ]
                            );
                            $count++;
                        }
                        $current->addDay();
                    }

                    Notification::make()
                        ->title("{$count} dias não letivos criados/atualizados!")
                        ->success()
                        ->send();
                })
                ->tooltip('Cria automaticamente finais de semana (Sábados/Domingos) e Feriados Nacionais Brasileiros considerados: 01/01, 21/04, 01/05, 07/09, 12/10, 02/11, 15/11, 20/11, 25/12 e Sexta-Feira Santa.'),
            CreateAction::make(),
            $this->ajudaAction('Dias Não Letivos', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📅', 'Dias Não Letivos', 'Datas sem aula (fins de semana, feriados e recessos) de cada período letivo.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja data, descrição, período letivo e se o dia está ativo.'],
                ['🪄', 'Gerar FDS e Feriados', 'Escolha um período letivo e o sistema cria sábados, domingos e os feriados nacionais.'],
                $user->can('Create:DiaNaoLetivo') ? ['🆕', 'Novo Dia', 'Cadastre um recesso, feriado local ou dia sem aula.'] : null,
                $user->can('Update:DiaNaoLetivo') ? ['✏️', 'Editar', 'Ajuste a descrição ou desative um dia não letivo.'] : null,
            ])
            ->dica('Rodar "Gerar FDS e Feriados" de novo atualiza os dias já criados, sem duplicar.')
            ->alerta('Feriados municipais e estaduais não são gerados automaticamente: cadastre-os manualmente.');
    }
}
