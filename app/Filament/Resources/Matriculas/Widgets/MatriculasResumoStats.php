<?php

namespace App\Filament\Resources\Matriculas\Widgets;

use App\Filament\Resources\Matriculas\Pages\ListMatriculas;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Resumo da listagem de matrículas: acompanha a aba, a busca e os filtros em uso.
 */
class MatriculasResumoStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListMatriculas::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery()->reorder();

        $total = (clone $query)->count();
        $comPendencias = (clone $query)->comPendencias()->count();
        $semResponsavel = (clone $query)->semResponsavel()->count();
        $semContrato = (clone $query)->doesntHave('contrato')->count();

        return [
            Stat::make('Matrículas na lista', $total)
                ->description('Conforme a aba, a busca e os filtros')
                ->descriptionIcon('heroicon-m-identification')
                ->color('primary'),
            Stat::make('Com pendências', $comPendencias)
                ->description('Responsável, cadastro ou documentos')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($comPendencias > 0 ? 'danger' : 'success'),
            Stat::make('Sem responsável', $semResponsavel)
                ->description('Aluno sem Pai, Mãe ou Responsável')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($semResponsavel > 0 ? 'warning' : 'success'),
            Stat::make('Sem contrato', $semContrato)
                ->description('Matrículas sem contrato gerado')
                ->descriptionIcon('heroicon-m-document-minus')
                ->color($semContrato > 0 ? 'warning' : 'success'),
        ];
    }
}
