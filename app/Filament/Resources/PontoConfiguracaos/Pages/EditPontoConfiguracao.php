<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoConfiguracaos\Pages;

use App\Exceptions\PontoException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PontoConfiguracaos\PontoConfiguracaoResource;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPontoConfiguracao extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = PontoConfiguracaoResource::class;

    /**
     * O modelo recusa um modo oficial com requisitos pendentes; aqui a recusa vira aviso na tela em vez de erro.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (PontoException $e) {
            Notification::make()->title('Configuração não salva')->body($e->getMessage())->danger()->persistent()->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar Configuração de Ponto', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('⏰', 'Editar Configuração de Ponto', 'Ajuste das regras e parâmetros da apuração eletrônica de ponto.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['⚙️', 'Parâmetros Operacionais', 'Modifique os limites de tolerância e políticas de banco de horas.'],
                ['🛡️', 'Modo do Ponto', 'Alterne entre controle interno e conformidade portaria MTP.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:PontoConfiguracao') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:PontoConfiguracao') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:PontoConfiguracao') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
