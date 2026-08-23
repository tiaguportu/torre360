<?php

namespace App\Filament\Widgets;

use App\Enums\SituacaoMatricula;
use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Models\Matricula;
use App\Traits\HasCustomWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MatriculasPendentesWidget extends BaseWidget
{
    use HasCustomWidgetShield;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // 1. Contagem de Matrículas sem responsáveis (pendência de responsáveis)
        $semResponsavelCount = Matricula::query()
            ->where('situacao', SituacaoMatricula::ATIVA)
            ->whereHas('pessoa', function ($query) {
                $query->whereDoesntHave('responsaveis');
            })
            ->count();

        // 2. Contagem de Matrículas com documentos obrigatórios pendentes (pendência de documentos)
        $documentosPendentesCount = 0;

        Matricula::query()
            ->where('situacao', SituacaoMatricula::ATIVA)
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

        // 3. Contagem de Matrículas com dados cadastrais ausentes (aluno, responsáveis ou financeiro)
        $cadastroPendenteCount = Matricula::query()
            ->where('situacao', SituacaoMatricula::ATIVA)
            ->comCadastroIncompleto()
            ->count();

        return [
            Stat::make('Pendência de Responsáveis', $semResponsavelCount)
                ->description('Matrículas sem responsável associado')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($semResponsavelCount > 0 ? 'danger' : 'success')
                ->url(MatriculaResource::getUrl('index', [
                    'filters[sem_responsavel][value]' => '1',
                    'filters[situacao][value]' => SituacaoMatricula::ATIVA->value,
                ])),

            Stat::make('Pendência de Documentos', $documentosPendentesCount)
                ->description('Documentos obrigatórios faltantes')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($documentosPendentesCount > 0 ? 'danger' : 'success')
                ->url(MatriculaResource::getUrl('index', [
                    'filters[documentos_pendentes][value]' => '1',
                    'filters[situacao][value]' => SituacaoMatricula::ATIVA->value,
                ])),

            Stat::make('Pendência de Cadastro', $cadastroPendenteCount)
                ->description('Alunos ou responsáveis com cadastro incompleto')
                ->descriptionIcon('heroicon-m-identification')
                ->color($cadastroPendenteCount > 0 ? 'danger' : 'success')
                ->url(MatriculaResource::getUrl('index', [
                    'filters[dados_pendentes][value]' => '1',
                    'filters[situacao][value]' => SituacaoMatricula::ATIVA->value,
                ])),
        ];
    }
}
