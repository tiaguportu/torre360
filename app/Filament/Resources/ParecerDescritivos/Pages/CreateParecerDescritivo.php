<?php

declare(strict_types=1);

namespace App\Filament\Resources\ParecerDescritivos\Pages;

use App\Exceptions\ParecerDescritivoException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ParecerDescritivos\ParecerDescritivoResource;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Services\ParecerDescritivoService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateParecerDescritivo extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = ParecerDescritivoResource::class;

    /**
     * O parecer nasce no serviço (matrícula ativa, etapa da turma do aluno, um por aluno e etapa). Se já existe um para
     * o aluno nesta etapa, abre o existente em vez de duplicar.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $parecer = app(ParecerDescritivoService::class)->iniciar(
                Matricula::findOrFail($data['matricula_id']),
                EtapaAvaliativa::findOrFail($data['etapa_avaliativa_id']),
                auth()->user(),
            );
        } catch (ParecerDescritivoException $e) {
            Notification::make()->title('Não foi possível iniciar o parecer')->body($e->getMessage())->danger()->send();

            $this->halt();
        }

        if (filled($data['texto'] ?? null) && ! $parecer->estaPublicado() && blank($parecer->texto)) {
            $parecer->update(['texto' => $data['texto']]);
        }

        return $parecer;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Parecer Descritivo', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📝', 'Novo Parecer Descritivo', 'Elaboração do parecer avaliativo individual do estudante.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['👤', 'Matrícula e Etapa', 'Selecione o estudante e o período avaliativo correspondente.'],
                ['✍️', 'Texto Descritivo', 'Redija o parecer detalhando o desenvolvimento integral do aluno.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:ParecerDescritivo') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:ParecerDescritivo') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:ParecerDescritivo') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
