<?php

namespace App\Filament\Resources\ConsentimentoMatriculas\Pages;

use App\Enums\StatusConsentimento;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ConsentimentoMatriculas\ConsentimentoMatriculaResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateConsentimentoMatricula extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = ConsentimentoMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Consentimento', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        $secao = [
            ['📋', 'Matrícula e Aluno', 'Selecione o aluno/matrícula para registrar o consentimento.'],
            ['🛡️', 'Tipo de Consentimento', 'Escolha a finalidade (ex.: Uso de Imagem Institucional, Redes Sociais).'],
            ['✍️', 'Status da Resposta', 'Defina se foi Autorizado, Não Autorizado ou Pendente.'],
        ];

        if ($user?->can('Create:ConsentimentoMatricula')) {
            $secao[] = ['💾', 'Gravação Manual', 'Grave a resposta colhida fisicamente da família.'];
        }

        return HelpContent::make('🛡️', 'Registrar Consentimento', 'Permite à secretaria lançar manualmente uma autorização colhida fora do Portal.')
            ->secao('🎯 O que você pode fazer?', $secao)
            ->dica('Caso a resposta seja colhida digitalmente pela família no Portal, o registro é gerado e atualizado de forma automática.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['status'] ?? null) !== StatusConsentimento::Pendente->value) {
            $data['respondido_em'] = now();
            $data['respondido_por_user_id'] = auth()->id();
        }

        return $data;
    }
}
