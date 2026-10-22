<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoConfiguracaos\Pages;

use App\Exceptions\PontoException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PontoConfiguracaos\PontoConfiguracaoResource;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePontoConfiguracao extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = PontoConfiguracaoResource::class;

    /**
     * O modelo recusa um modo oficial com requisitos pendentes; aqui a recusa vira aviso na tela em vez de erro.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (PontoException $e) {
            Notification::make()->title('Configuração não salva')->body($e->getMessage())->danger()->persistent()->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Nova Configuração de Ponto', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('⏰', 'Nova Configuração de Ponto', 'Definição de regras de tolerância, jornada e modo de registro de ponto por unidade.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['🏢', 'Unidade Escolar', 'Vincule a configuração à unidade de lotação dos funcionários.'],
                ['⏱️', 'Tolerância e Regras', 'Defina a tolerância em minutos para batidas diárias conforme CLT.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:PontoConfiguracao') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:PontoConfiguracao') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:PontoConfiguracao') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
