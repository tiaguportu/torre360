<?php

declare(strict_types=1);

namespace App\Filament\Resources\AvaliacaoDocentes\Pages;

use App\Exceptions\AvaliacaoDocenteException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AvaliacaoDocentes\AvaliacaoDocenteResource;
use App\Services\AvaliacaoDocenteService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAvaliacaoDocente extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = AvaliacaoDocenteResource::class;

    /**
     * A observação nasce no serviço, que valida o ciclo, a data e a autoavaliação e já cria uma linha por critério
     * do instrumento. Em seguida o avaliador cai direto na tela de notas.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(AvaliacaoDocenteService::class)->iniciar($data, auth()->user());
        } catch (AvaliacaoDocenteException $e) {
            Notification::make()
                ->title('Não foi possível iniciar a avaliação')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Avaliação iniciada: dê as notas aos critérios';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Iniciar Avaliação de Desempenho Docente', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📝', 'Iniciar Avaliação de Desempenho Docente', 'Iniciação de ciclo avaliativo de professor com base no instrumento selecionado.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['📋', 'Instrumento e Ciclo', 'Selecione o ciclo vigente e o instrumento avaliativo aplicável.'],
                ['👤', 'Professor', 'Escolha o docente a ser avaliado pela coordenação.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:AvaliacaoDocente') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:AvaliacaoDocente') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:AvaliacaoDocente') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]))
            ->dica('Após salvar, você será direcionado para atribuir as notas a cada critério avaliativo.');
    }
}
