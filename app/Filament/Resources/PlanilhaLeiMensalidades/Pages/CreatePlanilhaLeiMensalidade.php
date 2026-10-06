<?php

namespace App\Filament\Resources\PlanilhaLeiMensalidades\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanilhaLeiMensalidades\PlanilhaLeiMensalidadeResource;
use App\Services\PlanilhaLeiMensalidadeService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlanilhaLeiMensalidade extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = PlanilhaLeiMensalidadeResource::class;

    protected function getHeaderActions(): array
    {
        $conteudo = HelpContent::make(
            '🆕',
            'Nova Planilha da Lei da Mensalidade',
            'Preencha os custos reais do exercício base e informe as variações projetadas de dissídio e custeio. O sistema calcula a fórmula oficial da Lei 9.870/99 automaticamente.'
        )
            ->secao('⚙️ Dicas de Preenchimento', [
                ['📥', 'Importar do Sistema', 'Clique no botão superior para carregar a quantidade de alunos matriculados e custos de turmas cadastrados no Torre360.'],
                ['📈', 'Fórmula da Lei', 'O percentual sugerido é obtido por: (Variação Pessoal + Variação Custeio + Novos Investimentos) / Custos Base * 100.'],
            ]);

        return [
            Action::make('importarDadosSistema')
                ->label('Importar Dados do Sistema')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    $service = app(PlanilhaLeiMensalidadeService::class);
                    $anoBase = (int) ($this->data['ano_base'] ?? now()->year);
                    $unidadeId = $this->data['unidade_id'] ?? null;
                    $cursoId = $this->data['curso_id'] ?? null;

                    $historico = $service->importarHistoricoSistema($anoBase, $unidadeId, $cursoId);

                    $this->data = array_merge($this->data, $historico);
                    $this->form->fill($this->data);

                    Notification::make()
                        ->title('Dados do Sistema Carregados')
                        ->body("Importados {$historico['alunos_base']} alunos ativos e estrutura de custos média do ano {$anoBase}.")
                        ->success()
                        ->send();
                }),

            $this->ajudaAction('Criar Planilha Lei 9.870/99', $conteudo),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(PlanilhaLeiMensalidadeService::class);
        $data['responsavel_user_id'] = auth()->id();

        // Recalcula os índices garantindo precisão matemática
        $indices = $service->calcularIndices($data);
        $dadosConsolidados = array_merge($data, $indices);

        return static::getModel()::create($dadosConsolidados);
    }
}
