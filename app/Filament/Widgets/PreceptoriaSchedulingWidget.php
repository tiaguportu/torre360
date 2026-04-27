<?php

namespace App\Filament\Widgets;

use App\Models\Matricula;
use App\Models\Preceptoria;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PreceptoriaSchedulingWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        // Buscar todas as pessoas vinculadas ao usuário
        $pessoasIds = $user->pessoas()->pluck('pessoa.id')->toArray();

        if (empty($pessoasIds)) {
            return [];
        }

        // Buscar matrículas ativas vinculadas a essas pessoas (seja como aluno ou através de dependentes)
        // 1. Matrículas onde o usuário é o próprio aluno
        $matriculasProprias = Matricula::whereIn('pessoa_id', $pessoasIds)
            ->where('situacao', 'ativa')
            ->get();

        // 2. Matrículas onde o usuário é responsável por um aluno
        $matriculasDependentes = Matricula::whereHas('pessoa.responsaveis', function ($query) use ($pessoasIds) {
            $query->whereIn('responsavel_id', $pessoasIds);
        })
            ->where('situacao', 'ativa')
            ->get();

        $todasMatriculas = $matriculasProprias->concat($matriculasDependentes)->unique('id');

        $stats = [];

        foreach ($todasMatriculas as $matricula) {
            // Verificar se já tem preceptoria agendada futura
            $agendamentoFuturo = Preceptoria::where('matricula_id', $matricula->id)
                ->where('data', '>=', now()->toDateString())
                ->orderBy('data', 'asc')
                ->orderBy('hora_inicio', 'asc')
                ->first();

            if ($agendamentoFuturo) {
                $data = $agendamentoFuturo->data ? Carbon::parse($agendamentoFuturo->data)->format('d/m/Y') : '';
                $hora = $agendamentoFuturo->hora_inicio ? Carbon::parse($agendamentoFuturo->hora_inicio)->format('H:i') : '';
                $prof = current(explode(' ', $agendamentoFuturo->professor?->nome ?? 'S/P'));

                $stats[] = Stat::make($matricula->pessoa->nome, 'Preceptoria Agendada')
                    ->description("{$data} às {$hora} | Prof. {$prof}")
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success');
            } else {
                // IDs dos ciclos de preceptoria em que o aluno JÁ tem agendamento (passado ou futuro)
                $ciclosAgendadosQuery = Preceptoria::where('matricula_id', $matricula->id)
                    ->whereNotNull('ciclo_preceptoria_id')
                    ->select('ciclo_preceptoria_id');

                // Verificar se existem horários disponíveis no sistema em um ciclo que o aluno AINDA NÃO agendou
                $temJanelasDisponiveis = Preceptoria::whereNull('matricula_id')
                    ->where('data', '>=', now()->toDateString())
                    ->whereNotIn('ciclo_preceptoria_id', $ciclosAgendadosQuery)
                    ->exists();

                if ($temJanelasDisponiveis) {
                    $stats[] = Stat::make($matricula->pessoa->nome, 'Preceptoria Disponível')
                        ->description('Você pode agendar uma nova preceptoria.')
                        ->descriptionIcon('heroicon-m-calendar-days')
                        ->color('warning')
                        ->extraAttributes([
                            'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition',
                            'onclick' => "window.location.href='".route('filament.admin.resources.preceptorias.agendar')."?matricula_id={$matricula->id}'",
                        ]);
                }
            }
        }

        return $stats;
    }
}
