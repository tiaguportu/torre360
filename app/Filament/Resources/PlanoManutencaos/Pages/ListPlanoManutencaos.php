<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoManutencaos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoManutencaos\PlanoManutencaoResource;
use App\Services\ManutencaoPreventivaService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPlanoManutencaos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = PlanoManutencaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo plano'),
            Action::make('processar')
                ->label('Abrir ordens que já venceram a janela')
                ->icon('heroicon-o-play')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Faz agora o que o agendador faz todo dia: sincroniza as ordens concluídas e abre as dos planos que entraram na janela de antecedência.')
                ->visible(fn (): bool => (bool) auth()->user()?->can('Create:PlanoManutencao') && auth()->user()?->can('GerarOrdem:PlanoManutencao'))
                ->action(function (): void {
                    $servico = app(ManutencaoPreventivaService::class);
                    $sincronizadas = $servico->sincronizarComOrdensDeServico();
                    $abertas = $servico->gerarOrdensPendentes();

                    Notification::make()
                        ->title("{$abertas} ordem(ns) aberta(s), {$sincronizadas} execução(ões) sincronizada(s)")
                        ->success()
                        ->send();
                }),
            $this->ajudaAction('Manutenção Preventiva', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🛠️', 'Manutenção Preventiva', 'Planos recorrentes de manutenção de bens e espaços, que abrem ordens de serviço no prazo.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja os planos com o alvo, a periodicidade, o próximo vencimento e a situação (vencido, a vencer, em dia). O número no menu é a quantidade de planos vencidos.'],
                $user->can('Create:PlanoManutencao') ? ['🆕', 'Novo plano', 'Defina o que será mantido (bem, espaço ou ambos), a cada quantos dias, a próxima data e um checklist.'] : null,
                $user->can('GerarOrdem:PlanoManutencao') ? ['🔧', 'Gerar OS agora', 'Abre a ordem de serviço do próximo vencimento sem esperar a antecedência configurada.'] : null,
                $user->can('RegistrarExecucao:PlanoManutencao') ? ['✅', 'Registrar execução', 'Informe a data e o resultado do checklist. A ordem de serviço é concluída e o plano avança.'] : null,
                $user->can('Update:PlanoManutencao') ? ['📆', 'Reprogramar', 'Move o próximo vencimento quando a manutenção não pôde ser feita na data.'] : null,
            ])
            ->alerta('Todo dia o sistema abre, sozinho, a ordem de serviço dos planos que entraram na janela de antecedência. Se uma ordem for cancelada, o plano fica vencido até alguém reprogramá-lo (não abre outra ordem para o mesmo vencimento).')
            ->dica('Concluir a ordem de serviço na tela de Ordens de Serviço também fecha a execução e avança o plano.');
    }
}
