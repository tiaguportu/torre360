<?php

namespace App\Filament\Resources\ReguaFollowUps\Pages;

use App\Filament\Resources\ReguaFollowUps\ReguaFollowUpResource;
use App\Services\ReguaFollowUpService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ListReguaFollowUps extends ListRecords
{
    protected static string $resource = ReguaFollowUpResource::class;

    public function boot(): void
    {
        if (! Schema::hasTable('regua_follow_ups')) {
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova Automação')
                ->visible(fn () => auth()->user()?->can('Create:ReguaFollowUp') ?? false),

            Action::make('executar_regua')
                ->label('Executar Régua do Dia')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn () => auth()->user()?->can('Execute:ReguaFollowUp') ?? false)
                ->modalHeading('Processar Régua de Follow-up do CRM')
                ->modalDescription('O sistema analisará os leads e visitas agendadas que atendem aos gatilhos configurados e disparará as mensagens ou notificações correspondentes.')
                ->schema([
                    DatePicker::make('data_referencia')
                        ->label('Data de Referência')
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),

                    Toggle::make('dry_run')
                        ->label('Modo Simulação (Apenas contar, sem disparar e-mails reais)')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $dataRef = Carbon::parse($data['data_referencia']);
                    $dryRun = (bool) $data['dry_run'];

                    $resultado = app(ReguaFollowUpService::class)->processarReguaDiaria($dataRef, $dryRun);

                    Notification::make()
                        ->title($dryRun ? 'Simulação da Régua Concluída!' : 'Régua de Follow-up Processada!')
                        ->body("Total de {$resultado['total_notificacoes_enviadas']} mensagem(ns) gerada(s) a partir de {$resultado['total_candidatos_analisados']} candidato(s) avaliado(s).")
                        ->success()
                        ->send();
                }),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Régua de Automação de Follow-up (CRM)')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $canCreate = $user?->can('Create:ReguaFollowUp') ?? false;
        $canExecute = $user?->can('Execute:ReguaFollowUp') ?? false;

        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">A <strong>Régua de Follow-up do CRM</strong> automatiza o relacionamento e a comunicação com famílias interessadas em matricular seus filhos, garantindo que nenhum lead esfrie ou seja esquecido.</p>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Gatilhos automáticos disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Boas-vindas ao Novo Lead:</strong> Envia e-mail de acolhimento imediatamente após o lead entrar pelo formulário do site ou ser cadastrado.</li>';
        $html .= '<li><strong>Lembrete de Visita Agendada (D-X):</strong> Dispara lembrete aos pais X dias antes da visita marcada, com horários e orientações da portaria.</li>';
        $html .= '<li><strong>Pós-Visita Realizada:</strong> Agradece a presença da família no dia seguinte à visita presencial, reforçando o projeto pedagógico e incentivando a matrícula.</li>';
        $html .= '<li><strong>Recuperação de Falta (No-Show):</strong> Dispara mensagem empática convidando para reagendamento caso a família não tenha comparecido.</li>';
        $html .= '<li><strong>Lead Estagnado (Sem Interação):</strong> Emite um alerta interno para o consultor quando um lead passa X dias sem qualquer contato registrado.</li>';
        $html .= '<li><strong>Retorno de Contato Atrasado:</strong> Alerta quando a data combinada de próximo contato estiver vencida.</li>';
        $html .= '</ul>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Canais de Envio:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>E-mail para a Família:</strong> Dispara e-mail com layout profissional e tags dinâmicas. Respeita automaticamente a LGPD (opt-out de comunicação).</li>';
        $html .= '<li><strong>Alerta no Painel (Sininho):</strong> Notificação interna direta para o consultor responsável pelo lead (ou administradores).</li>';
        $html .= '</ul>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Funcionalidades acessíveis ao seu perfil:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li>Visualização da lista de automações ativas e total de disparos efetuados.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Nova Automação:</strong> Permite cadastrar novas regras personalizadas por série, origem ou etapa.</li>';
        }

        if ($canExecute) {
            $html .= '<li><strong>Executar Régua do Dia:</strong> Permite processar os disparos sob demanda ou executar em modo simulação (Dry-run).</li>';
            $html .= '<li><strong>Ação Testar:</strong> Dispara uma mensagem de teste usando os dados de um lead real para conferir a formatação.</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
