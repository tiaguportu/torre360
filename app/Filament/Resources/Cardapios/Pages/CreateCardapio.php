<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cardapios\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Cardapios\CardapioResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateCardapio extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = CardapioResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Cardápio Escolar', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🍲', 'Novo Cardápio Escolar', 'Cadastro de cardápio e refeições semanais por unidade de ensino.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['📅', 'Período e Unidade', 'Defina a semana de vigência e a unidade escolar contemplada.'],
                ['🥗', 'Refeições e Alérgenos', 'Cadastre o menu diário e marque eventuais alérgenos para aviso automático.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:Cardapio') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:Cardapio') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:Cardapio') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]))
            ->alerta('Verifique os alérgenos marcados para alertar automaticamente os responsáveis de alunos com restrição alimentar.');
    }
}
