<?php

namespace App\Filament\Resources\Matriculas\Actions;

use App\Models\Matricula;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;

/**
 * Exclusão em lote que apaga só as matrículas sem histórico e informa quantas foram mantidas
 * (ver `ExcluirMatriculaAction` e `Matricula::historicoQueImpedeExclusao()`).
 */
class ExcluirMatriculasEmLoteAction extends DeleteBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->modalDescription('Matrículas com histórico (frequência, notas, resultado final, contrato, bolsas e similares) não são excluídas: use o Cancelamento de Matrícula.');

        $this->using(function (Collection $records, DeleteBulkAction $action): void {
            /** @var Collection<int, Matricula> $records */
            foreach ($records as $record) {
                if ($record->possuiHistorico()) {
                    $action->reportBulkProcessingFailure(
                        'com_historico',
                        fn (int $count): string => trans_choice('{1} :count matrícula tem histórico e foi mantida; use o Cancelamento de Matrícula.|[2,*] :count matrículas têm histórico e foram mantidas; use o Cancelamento de Matrícula.', $count, ['count' => $count]),
                    );

                    continue;
                }

                $record->delete() || $action->reportBulkProcessingFailure();
            }
        });
    }
}
