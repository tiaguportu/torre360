<?php

namespace App\Filament\Resources\SacolasLeitura\Pages;

use App\Filament\Resources\SacolasLeitura\SacolaLeituraResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateSacolaLeitura extends CreateRecord
{
    protected static string $resource = SacolaLeituraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Sacola de Leitura')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'sacolas-leitura-create')->first(),
                        ]),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return SacolaLeituraResource::getUrl('gerenciar', ['record' => $this->record]);
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>Selecione a <strong>Turma</strong> e o <strong>Professor Responsável</strong> para abrir a nova sacola de leitura.</p>';
        $html .= '<p>Ao clicar em <strong>Criar</strong>, você será direcionado para a tela onde poderá bipar sucessivamente os livros que irão compor a sacola (usando leitor de código de barras ou a câmera do celular).</p>';

        if ($user?->can('Create:SacolaLeitura')) {
            $html .= '<p class="text-xs text-emerald-600 dark:text-emerald-400">✓ Você possui permissão para registrar novas sacolas de leitura.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
