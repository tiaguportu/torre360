<?php

namespace App\Filament\Resources\ReguaFollowUps\Pages;

use App\Filament\Resources\ReguaFollowUps\ReguaFollowUpResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CreateReguaFollowUp extends CreateRecord
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
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Automação de Follow-up')
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
        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Ao cadastrar uma nova regra de follow-up, configure o momento do gatilho e o conteúdo da mensagem personalizada.</p>';
        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Exemplos Práticos:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Lembrete 2 dias antes da visita:</strong> Escolha <em>Lembrete de Visita Agendada</em> e defina o intervalo como <code>2</code>.</li>';
        $html .= '<li><strong>Pesquisa 1 dia após a visita:</strong> Escolha <em>Pós-Visita Realizada</em> e defina o intervalo como <code>1</code>.</li>';
        $html .= '<li><strong>Boas-vindas imediato:</strong> Escolha <em>Novo Lead Cadastrado</em> e defina o intervalo como <code>0</code>.</li>';
        $html .= '</ul>';
        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mt-2">Tags dinâmicas para o texto:</h4>';
        $html .= '<p class="text-xs text-gray-500 font-mono bg-gray-100 dark:bg-gray-800 p-2 rounded">{{NOME_RESPONSAVEL}} ou [Nome], {{NOME_ALUNO}} ou [Aluno], {{SERIE_INTERESSE}} ou [Serie], {{NOME_CONSULTOR}} ou [Consultor], {{DATA_VISITA}} ou [DataVisita], {{HORARIO_VISITA}} ou [HorarioVisita], {{ESCOLA_NOME}} ou [Escola]</p>';
        $html .= '</div>';

        return $html;
    }
}
