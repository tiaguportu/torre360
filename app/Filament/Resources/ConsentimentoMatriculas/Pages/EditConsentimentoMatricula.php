<?php

namespace App\Filament\Resources\ConsentimentoMatriculas\Pages;

use App\Enums\StatusConsentimento;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ConsentimentoMatriculas\ConsentimentoMatriculaResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConsentimentoMatricula extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = ConsentimentoMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->ajudaAction('Editar Consentimento', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        $secao = [
            ['✏️', 'Alteração de Status', 'Atualize se o consentimento foi autorizado ou revogado pelo responsável.'],
            ['📅', 'Vigência', 'Acompanhe a data em que a resposta foi registrada e a validade.'],
        ];

        if ($user?->can('Delete:ConsentimentoMatricula')) {
            $secao[] = ['🗑️', 'Exclusão', 'Exclua este registro se ele tiver sido criado indevidamente.'];
        }

        return HelpContent::make('🛡️', 'Editar Consentimento', 'Permite atualizar os dados do consentimento registrado para a matrícula.')
            ->secao('🎯 O que você pode fazer?', $secao)
            ->dica('Ao alterar o status para Autorizado ou Não Autorizado, o sistema grava automaticamente quem realizou a alteração e a data atual.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $statusMudou = ($data['status'] ?? null) !== $this->record->status?->value;

        if ($statusMudou && ($data['status'] ?? null) !== StatusConsentimento::Pendente->value) {
            $data['respondido_em'] = now();
            $data['respondido_por_user_id'] = auth()->id();
        }

        return $data;
    }
}
