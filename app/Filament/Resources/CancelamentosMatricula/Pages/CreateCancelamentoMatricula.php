<?php

declare(strict_types=1);

namespace App\Filament\Resources\CancelamentosMatricula\Pages;

use App\Enums\OrigemCancelamentoMatricula;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CancelamentosMatricula\CancelamentoMatriculaResource;
use App\Models\CancelamentoMatricula;
use App\Models\Matricula;
use App\Services\CancelamentoMatriculaService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCancelamentoMatricula extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = CancelamentoMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Registrar pedido de cancelamento', HelpContent::make('📄', 'Registrar pedido de cancelamento', 'Use quando a família avisou por telefone ou pessoalmente.')
                ->secao('🎯 Como preencher', [
                    ['🎓', 'Matrícula', 'Aparecem só as matrículas que ainda podem ser canceladas e não têm pedido em andamento.'],
                    ['📅', 'Data de saída', 'O último dia do aluno na escola. Aqui pode ser uma data anterior a hoje, se a família já saiu. O mês da saída é cobrado inteiro.'],
                    ['🔒', 'Detalhes do motivo', 'Ficam guardados criptografados e não aparecem no histórico de alterações. Escreva só o necessário.'],
                    ['📑', 'Declaração de Transferência', 'Marque se o aluno vai para outra escola: ela é emitida ao concluir, mesmo com débitos.'],
                ])
                ->dica('Depois de salvar, abra "Situação financeira" e "Aprovar" na lista para decidir o destino de cada fatura.')),
        ];
    }

    /**
     * Passa pelo serviço, que trava a matrícula, recusa duplicidade e registra o log.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $matricula = Matricula::query()->findOrFail($data['matricula_id']);

        try {
            return app(CancelamentoMatriculaService::class)->solicitar($matricula, $data, auth()->user(), OrigemCancelamentoMatricula::Escola);
        } catch (\DomainException $e) {
            Notification::make()->title('Não foi possível registrar o pedido')->body($e->getMessage())->danger()->send();

            $this->halt();
        }

        // halt() lança a exceção que interrompe a criação; este retorno só satisfaz a assinatura.
        return new CancelamentoMatricula;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
