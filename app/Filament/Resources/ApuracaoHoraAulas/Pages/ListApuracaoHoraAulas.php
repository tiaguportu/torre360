<?php

namespace App\Filament\Resources\ApuracaoHoraAulas\Pages;

use App\Filament\Resources\ApuracaoHoraAulas\ApuracaoHoraAulaResource;
use App\Models\Pessoa;
use App\Services\ApuracaoHoraAulaService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListApuracaoHoraAulas extends ListRecords
{
    protected static string $resource = ApuracaoHoraAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apurarMes')
                ->label('Processar Mês')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->form([
                    TextInput::make('ano')
                        ->label('Ano')
                        ->numeric()
                        ->default(now()->year)
                        ->required(),

                    Select::make('mes')
                        ->label('Mês')
                        ->options([
                            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
                            4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
                            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
                            10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                        ])
                        ->default(now()->month)
                        ->required(),

                    TextInput::make('valor_hora_aula')
                        ->label('Valor Base da Hora-Aula (R$)')
                        ->numeric()
                        ->default(45.00)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $service = app(ApuracaoHoraAulaService::class);
                    $professores = Pessoa::has('cronogramasAulas')->get();

                    foreach ($professores as $prof) {
                        $service->apurarProfessor(
                            $prof,
                            (int) $data['ano'],
                            (int) $data['mes'],
                            (float) $data['valor_hora_aula'],
                            0.0,
                            auth()->user()
                        );
                    }

                    Notification::make()
                        ->title("Apuração de {$professores->count()} professores concluída para {$data['mes']}/{$data['ano']}.")
                        ->success()
                        ->send();
                }),

            CreateAction::make(),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Apuração de Hora-Aula Docente')
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
        $activeRole = session('active_role');

        $canCreate = $user ? $user->can('Create:ApuracaoHoraAula') : false;
        $canDelete = $user ? $user->can('Delete:ApuracaoHoraAula') : false;

        $html = '<p>Esta tela realiza a apuração mensal de <strong>horas-aulas ministradas</strong> pelo corpo docente conforme as diretrizes da CLT (Arts. 317 a 324) e convenções coletivas de trabalho.</p>';
        $html .= '<h3>Como funciona:</h3><ul>';
        $html .= '<li><strong>Cômputo Realizado:</strong> O sistema cruza as aulas do cronograma com as chamadas de frequência lançadas pelo professor. Aulas dadas e reposições da Onda 49 contam como horas cumpridas.</li>';
        $html .= '<li><strong>Processar Mês:</strong> Utilize o botão <em>"Processar Mês"</em> no cabeçalho para varrer as chamadas de todos os professores no período e calcular o valor bruto com DSR (1/6).</li>';
        $html .= '<li><strong>Homologação:</strong> A coordenação e o RH conferem as apurações e acionam <em>"Homologar"</em> para travar a competência e exportar os dados para a folha contábil.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Lançamento Manual:</strong> Se necessário, inclua ajustes ou horas-atividade manuais clicando em <em>"Novo Apuração de Hora-Aula"</em>.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Exclusão:</strong> Registros indevidos podem ser excluídos por usuários autorizados.</li>';
        }

        $html .= '</ul>';

        if ($activeRole) {
            $html .= "<p class='text-xs text-gray-500 mt-2'>Papel ativo atual: <strong>{$activeRole}</strong></p>";
        }

        return $html;
    }
}
