<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosAssinatura\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\DocumentosAssinatura\Actions\AcoesDocumentoAssinatura;
use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Support\HelpContent;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentoAssinatura extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = DocumentoAssinaturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AcoesDocumentoAssinatura::enviar()->after(fn () => $this->recarregar()),
            AcoesDocumentoAssinatura::atualizar()->after(fn () => $this->recarregar()),
            ActionGroup::make([
                AcoesDocumentoAssinatura::links(),
                AcoesDocumentoAssinatura::baixarOriginal(),
                AcoesDocumentoAssinatura::baixarAssinado(),
                AcoesDocumentoAssinatura::cancelar()->after(fn () => $this->recarregar()),
            ])->label('Mais')->icon('heroicon-o-ellipsis-vertical')->color('gray')->button(),
            $this->ajudaAction('Documento para assinatura', HelpContent::make('✍️', 'Documento para assinatura', 'Acompanhe quem já assinou e baixe os PDFs.')
                ->secao('🎯 Como usar', [
                    ['📤', 'Enviar para assinatura', 'Manda o PDF para o Assinafy, que envia um e-mail a cada assinante. Se der erro, corrija e envie de novo: o que já foi cadastrado lá não se repete.'],
                    ['🔄', 'Atualizar situação', 'Consulta o Assinafy na hora. O sistema também consulta sozinho a cada hora e quando o Assinafy avisa.'],
                    ['🔗', 'Links de assinatura', 'O endereço de assinatura de cada pessoa, para mandar por outro meio se o e-mail não chegou.'],
                    ['📄', 'PDF enviado e PDF assinado', 'O primeiro é o que foi para assinatura; o segundo, com o certificado, aparece quando todos assinam.'],
                    ['🚫', 'Cancelar', 'Quem ainda não assinou deixa de poder assinar. Não desfaz assinaturas já dadas.'],
                ])
                ->alerta('O texto do documento não muda depois de criado. Para corrigir um erro, cancele e crie outro.')),
        ];
    }

    private function recarregar(): void
    {
        $this->getRecord()->refresh();
        $this->refreshFormData(['status', 'enviado_em', 'concluido_em', 'arquivo_assinado_path']);
    }
}
