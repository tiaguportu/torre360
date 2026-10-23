<?php

namespace App\Filament\Resources\NotificacoesConselhoTutelar\Pages;

use App\Filament\Resources\NotificacoesConselhoTutelar\NotificacaoConselhoTutelarResource;
use App\Models\NotificacaoConselhoTutelar;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateNotificacaoConselhoTutelar extends CreateRecord
{
    protected static string $resource = NotificacaoConselhoTutelarResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();
        if (empty($data['numero_oficio'])) {
            $data['numero_oficio'] = NotificacaoConselhoTutelar::gerarNumeroOficio();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Notificação ao Conselho Tutelar')
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
        return '<p>Cadastre uma notificação de infrequência escolar. O número do ofício será gerado automaticamente pelo sistema caso não seja preenchido.</p>'
            .'<ul>'
            .'<li><strong>Aluno e Turma:</strong> Selecione a turma e o estudante correspondente.</li>'
            .'<li><strong>Estatísticas:</strong> Informe a contagem de faltas e presenças para embasar o ofício legal.</li>'
            .'</ul>';
    }
}
