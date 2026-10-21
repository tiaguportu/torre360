<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes\Pages;

use App\Enums\ArredondamentoReajuste;
use App\Exceptions\ReajusteMensalidadeException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CenarioReajustes\CenarioReajusteResource;
use App\Models\PeriodoRematricula;
use App\Models\PlanilhaLeiMensalidade;
use App\Services\ReajusteMensalidadeService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCenarioReajuste extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = CenarioReajusteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Nova simulação de reajuste', HelpContent::make('🧮', 'Nova simulação de reajuste', 'Escolha a campanha de rematrícula e o percentual. O sistema monta uma linha por série.')
                ->passos('📖 Como preencher', [
                    'Escolha a campanha de rematrícula: o valor atual de cada série vem da média dos contratos do período de origem dela.',
                    'Informe o percentual padrão e o arredondamento (centavo, real ou R$ 10).',
                    'Vincule a planilha de custos da Lei das Mensalidades, se já existir: o sistema compara o percentual com o dela.',
                    'Clique em Criar. Na tela seguinte, ajuste as séries e confira a projeção antes de aprovar.',
                ])
                ->dica('Nada muda para as famílias enquanto o cenário não for aprovado e aplicado na campanha.')),
        ];
    }

    /**
     * O cenário nasce no serviço, que já monta um item por série a partir dos contratos do período de origem.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $cenario = app(ReajusteMensalidadeService::class)->simular(
                PeriodoRematricula::query()->findOrFail($data['periodo_rematricula_id']),
                (string) $data['nome'],
                (float) $data['percentual_padrao'],
                $data['arredondamento'] instanceof ArredondamentoReajuste ? $data['arredondamento'] : ArredondamentoReajuste::from($data['arredondamento']),
                filled($data['planilha_lei_id'] ?? null) ? PlanilhaLeiMensalidade::query()->find($data['planilha_lei_id']) : null,
                auth()->user(),
            );
        } catch (ReajusteMensalidadeException $e) {
            Notification::make()->title('Não foi possível simular')->body($e->getMessage())->danger()->persistent()->send();

            $this->halt();
        }

        if (filled($data['observacao'] ?? null)) {
            $cenario->update(['observacao' => $data['observacao']]);
        }

        return $cenario;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
