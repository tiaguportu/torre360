<?php

namespace App\Filament\Resources\AcordoInadimplencias\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AcordoInadimplencias\AcordoInadimplenciaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAcordoInadimplencias extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = AcordoInadimplenciaResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        $conteudo = HelpContent::make(
            '💳',
            'Central de Acordos e Recuperação de Inadimplência',
            'Gerencie a renegociação facilitada de mensalidades em atraso, com emissão de Termos de Confissão de Dívida com eficácia de Título Executivo Extrajudicial (Art. 784, III do CPC).'
        )
            ->secao('💡 Funcionalidades Principais', [
                ['📱', 'Envio por WhatsApp', 'Envie propostas de parcelamento amigável diretamente para o responsável com mensagem pronta e link seguro.'],
                ['✍️', 'Assinatura Online', 'A família pode formalizar o acordo pelo celular com registro de IP e data/hora do aceite sem precisar ir à escola.'],
                ['💰', 'Baixa de Parcelas', 'Utilize a ação de baixa rápida para quitar parcelas pagas via Pix, dinheiro ou cartão.'],
            ]);

        $acoes = [];

        if ($user && $user->can('Create:AcordoInadimplencia')) {
            $acoes[] = CreateAction::make();
        }

        $acoes[] = $this->ajudaAction('Acordos e Inadimplência', $conteudo);

        return $acoes;
    }
}
