<?php

namespace App\Filament\Resources\Inventarios\Pages;

use App\Filament\Resources\Inventarios\InventarioResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateInventario extends CreateRecord
{
    protected static string $resource = InventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Inventário')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'inventarios-create')->first(),
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
        return InventarioResource::getUrl('conferencia', ['record' => $this->record]);
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>Informe um nome descritivo para abrir a nova auditoria de acervo da biblioteca (ex: <em>Inventário Anual 2026</em>).</p>';
        $html .= '<p>Ao clicar em <strong>Criar</strong>, você será direcionado imediatamente para a tela de conferência rápida com leitor óptico.</p>';

        if ($user?->can('Create:InventarioAcervo')) {
            $html .= '<p class="text-xs text-emerald-600 dark:text-emerald-400">✓ Você possui permissão para abrir novos inventários.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
