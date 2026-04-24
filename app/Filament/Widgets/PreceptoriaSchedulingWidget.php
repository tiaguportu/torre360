<?php

namespace App\Filament\Widgets;

use App\Models\Matricula;
use App\Models\Preceptoria;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
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

        if (!$user) {
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
            $temAgendamentoFuturo = Preceptoria::where('matricula_id', $matricula->id)
                ->where('data', '>=', now()->toDateString())
                ->exists();

            if (!$temAgendamentoFuturo) {
                // Verificar se existem horários disponíveis no sistema
                $temJanelasDisponiveis = Preceptoria::whereNull('matricula_id')
                    ->where('data', '>=', now()->toDateString())
                    ->exists();

                if ($temJanelasDisponiveis) {
                    $stats[] = Stat::make($matricula->pessoa->nome, 'Preceptoria Disponível')
                        ->description('Você pode agendar uma nova preceptoria.')
                        ->descriptionIcon('heroicon-m-calendar-days')
                        ->color('warning')
                        ->extraAttributes([
                            'class' => 'cursor-pointer',
                            'onclick' => "window.location.href='" . route('filament.admin.resources.preceptorias.agendar') . "?matricula_id={$matricula->id}'",
                        ]);
                }
            }
        }

        return $stats;
    }
}
