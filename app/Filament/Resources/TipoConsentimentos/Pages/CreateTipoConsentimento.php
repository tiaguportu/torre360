<?php

namespace App\Filament\Resources\TipoConsentimentos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TipoConsentimentos\TipoConsentimentoResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoConsentimento extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = TipoConsentimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Tipo de Consentimento', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        $secao = [
            ['📝', 'Nome e Identificação', 'Defina o nome da finalidade (ex.: Autorização de Uso de Imagem e Voz).'],
            ['📜', 'Termo e Justificativa Legal', 'Escreva a descrição clara das finalidades de tratamento para a família.'],
            ['⏱️', 'Periodicidade / Renovação', 'Informe se este consentimento expira a cada N meses ou se vale por todo o ano letivo.'],
        ];

        if ($user?->can('Create:TipoConsentimento')) {
            $secao[] = ['💾', 'Ativação Imediata', 'Salve para disponibilizar este consentimento no Portal da Família.'];
        }

        return HelpContent::make('🛡️', 'Novo Tipo de Consentimento', 'Cadastre uma nova categoria de autorização ou consentimento LGPD para as famílias.')
            ->secao('🎯 O que você pode fazer?', $secao)
            ->dica('Tipos marcados como ativos passam a ser exibidos automaticamente no Portal para os responsáveis assinarem.');
    }
}
