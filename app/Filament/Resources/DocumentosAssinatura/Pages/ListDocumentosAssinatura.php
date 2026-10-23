<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosAssinatura\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Filament\Resources\TemplateDocumentos\TemplateDocumentoResource;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentosAssinatura extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = DocumentoAssinaturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo documento'),
            Action::make('modelos')
                ->label('Modelos de documentos')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->can('ViewAny:TemplateDocumento'))
                ->url(fn (): string => TemplateDocumentoResource::getUrl('index')),
            $this->ajudaAction('Documentos para Assinatura', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('✍️', 'Documentos para Assinatura', 'Mande um documento do sistema para a assinatura eletrônica pelo Assinafy.')
            ->passos('🔁 Como funciona', [
                'Clique em "Novo documento", escolha o modelo e o aluno, o cancelamento ou o funcionário de quem são os dados.',
                'Confira a lista de assinantes (o sistema sugere a família e o representante da escola) e, se quiser, defina um prazo.',
                'O sistema monta o PDF com o texto do modelo. Abra o documento, confira e clique em "Enviar para assinatura".',
                'Cada assinante recebe um e-mail do Assinafy. Acompanhe aqui quem já assinou; o sistema consulta o Assinafy sozinho a cada hora.',
                'Quando todos assinam, o PDF com o certificado fica guardado aqui e o cancelamento ou a admissão ligados ao documento são atualizados.',
            ])
            ->secao('📌 Bom saber', [
                ['📝', 'O texto vem do modelo', 'Para mudar o texto de um documento, edite o modelo em Modelos de Documentos. Documentos já criados guardam uma cópia do texto e não mudam.'],
                ['🔗', 'Links de assinatura', 'Se alguém não achar o e-mail, abra o documento e use "Links de assinatura" para mandar o endereço por outro meio. O link é só daquela pessoa.'],
                ['🔄', 'Atualizar situação', 'Consulta o Assinafy na hora. Use se a pessoa avisou que assinou e a tela ainda não mostra.'],
                ['🚫', 'Cancelar', 'Quem ainda não assinou deixa de poder assinar. Não desfaz assinaturas já dadas.'],
            ])
            ->alerta('Os modelos são pontos de partida: o jurídico da escola deve revisar o texto antes de ativar o modelo. Um documento enviado não pode ser editado; para corrigir, cancele e crie outro.')
            ->dica('Documentos de funcionário (contrato de trabalho, termos) só aparecem para quem também vê Admissão e Desligamento.');
    }
}
