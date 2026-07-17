<?php

namespace App\Filament\Widgets;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Role;

class MatriculasPendentesWidget extends BaseWidget
{
    use HasWidgetShield;

    public static function canView(): bool
    {
        $activeRole = session('active_role');

        if (! $activeRole) {
            return false;
        }

        if ($activeRole === 'super_admin') {
            return true;
        }

        try {
            $role = Role::findByName($activeRole, 'web');

            return $role->hasPermissionTo(static::getPermissionName());
        } catch (\Exception $e) {
            return false;
        }
    }

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // 1. Contagem de Matrículas sem responsáveis (pendência de responsáveis)
        $semResponsavelCount = Matricula::query()
            ->whereIn('situacao', [SituacaoMatricula::ATIVA, SituacaoMatricula::PENDENTE])
            ->whereHas('pessoa', function ($query) {
                $query->whereDoesntHave('responsaveis');
            })
            ->count();

        // 2. Contagem de Matrículas com documentos obrigatórios pendentes (pendência de documentos)
        $documentosPendentesCount = 0;

        Matricula::query()
            ->whereIn('situacao', [SituacaoMatricula::ATIVA, SituacaoMatricula::PENDENTE])
            ->with([
                'turma.serie.curso.documentos',
                'turma.tiposDocumentos',
                'tiposDocumentos',
                'documentoInseridos',
            ])
            ->chunk(150, function ($chunk) use (&$documentosPendentesCount) {
                foreach ($chunk as $matricula) {
                    if ($matricula->hasMissingMandatoryDocuments()) {
                        $documentosPendentesCount++;
                    }
                }
            });

        return [
            Stat::make('Pendência de Responsáveis', $semResponsavelCount)
                ->description('Matrículas sem responsável associado')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($semResponsavelCount > 0 ? 'danger' : 'success'),

            Stat::make('Pendência de Documentos', $documentosPendentesCount)
                ->description('Documentos obrigatórios faltantes')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($documentosPendentesCount > 0 ? 'danger' : 'success'),
        ];
    }
}
