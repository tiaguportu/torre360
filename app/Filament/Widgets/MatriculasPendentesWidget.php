<?php

namespace App\Filament\Widgets;

use App\Enums\SituacaoMatricula;
use App\Enums\TipoPendenciaMatricula;
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

        // 4. Matrículas ativas ou pendentes sem contrato gerado e com contrato ainda não assinado
        $contratoNaoGeradoCount = Matricula::query()->comContratoNaoGerado()->count();
        $contratoNaoAssinadoCount = Matricula::query()->comContratoNaoAssinado()->count();

        return [
            Stat::make('Pendência de Responsáveis', $semResponsavelCount)
                ->description('Matrículas sem responsável associado')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($semResponsavelCount > 0 ? 'danger' : 'success')
                ->url(MatriculaResource::getUrl('index', [
                    'activeTab' => 'ativas',
                    'filters[pendencias][values][0]' => TipoPendenciaMatricula::SEM_RESPONSAVEL->value,
                ])),

            Stat::make('Pendência de Documentos', $documentosPendentesCount)
                ->description('Documentos obrigatórios faltantes')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($documentosPendentesCount > 0 ? 'danger' : 'success')
                ->url(MatriculaResource::getUrl('index', [
                    'activeTab' => 'ativas',
                    'filters[pendencias][values][0]' => TipoPendenciaMatricula::DOCUMENTOS_FALTANDO->value,
                ])),

            Stat::make('Pendência de Cadastro', $cadastroPendenteCount)
                ->description('Alunos ou responsáveis com cadastro incompleto')
                ->descriptionIcon('heroicon-m-identification')
                ->color($cadastroPendenteCount > 0 ? 'danger' : 'success')
                ->url(MatriculaResource::getUrl('index', [
                    'activeTab' => 'ativas',
                    'filters[pendencias][values][0]' => TipoPendenciaMatricula::CADASTRO_INCOMPLETO->value,
                ])),

            // Valem para matrículas ativas e pendentes, por isso levam à aba "Com pendências"
            Stat::make('Contrato não gerado', $contratoNaoGeradoCount)
                ->description('Matrículas ativas ou pendentes sem contrato')
                ->descriptionIcon('heroicon-m-document-plus')
                ->color($contratoNaoGeradoCount > 0 ? 'warning' : 'success')
                ->url(MatriculaResource::getUrl('index', [
                    'activeTab' => 'com_pendencias',
                    'filters[pendencias][values][0]' => TipoPendenciaMatricula::CONTRATO_NAO_GERADO->value,
                ])),

            Stat::make('Contrato não assinado', $contratoNaoAssinadoCount)
                ->description('Contrato gerado e ainda sem assinatura')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($contratoNaoAssinadoCount > 0 ? 'info' : 'success')
                ->url(MatriculaResource::getUrl('index', [
                    'activeTab' => 'com_pendencias',
                    'filters[pendencias][values][0]' => TipoPendenciaMatricula::CONTRATO_NAO_ASSINADO->value,
                ])),
        ];
    }
}
