<?php

namespace App\Filament\Resources\Denuncias\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Denuncias\DenunciaResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\ListRecords;

class ListDenuncias extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = DenunciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Ouvidoria', $this->conteudoDaAjuda()),
        ];
    }

    private function conteudoDaAjuda(): HelpContent
    {
        return HelpContent::make('🛡️', 'Ouvidoria e convivência escolar', 'As manifestações recebidas pelo canal público /ouvidoria, só para o comitê da categoria.')
            ->passos('🚀 Como tratar uma manifestação', [
                'Abra a manifestação e use "Assumir a apuração".',
                'Registre cada passo em "Registrar andamento": parecer interno e comunicação ao Conselho Tutelar só o comitê vê; a devolutiva o autor lê pelo código.',
                'Ao terminar, use "Encerrar" e escreva a conclusão que o autor vai ler.',
            ])
            ->secao('⚠️ Atenção', [
                ['🚨', 'Suspeita de violência', 'Tem prazo de 2 dias e aparece marcada. A comunicação ao Conselho Tutelar segue o procedimento definido com o jurídico da escola.'],
                ['🔒', 'Sigilo', 'Quem não é do comitê da categoria não vê a manifestação. Nunca copie o relato para e-mail ou conversa.'],
            ])
            ->dica('O relato fica criptografado e a manifestação anônima não guarda quem enviou. Não tente identificar o autor.');
    }
}
