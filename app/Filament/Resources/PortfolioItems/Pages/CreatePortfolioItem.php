<?php

declare(strict_types=1);

namespace App\Filament\Resources\PortfolioItems\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PortfolioItems\PortfolioItemResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreatePortfolioItem extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = PortfolioItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registrado_por_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Item de Portfólio', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🎨', 'Novo Item de Portfólio', 'Registro de atividade, projeto ou evidência de aprendizagem do aluno.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['👤', 'Aluno e Turma', 'Associe o projeto à matrícula do estudante correspondente.'],
                ['📷', 'Mídias e Arquivos', 'Faça upload de fotos, vídeos ou documentos da atividade.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:PortfolioItem') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:PortfolioItem') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:PortfolioItem') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
