<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusDocumentoAssinatura;
use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Models\DocumentoAssinatura;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Documentos que a escola montou para assinatura eletrônica (Onda 69) e ainda não conseguiu mandar: rascunhos esquecidos e
 * envios que o Assinafy recusou. Documento de RH só entra para quem também vê Admissão e Desligamento.
 */
final class DocumentosParaAssinatura implements FontePendencia
{
    public function chave(): string
    {
        return 'documentos_para_assinatura';
    }

    public function titulo(): string
    {
        return 'Documentos para enviar à assinatura';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-pencil';
    }

    public function orientacao(): string
    {
        return 'Abra o documento, confira o texto e os assinantes e envie. Se o envio deu erro, o motivo aparece na página do documento.';
    }

    public function permissao(): string
    {
        return 'Enviar:DocumentoAssinatura';
    }

    public function url(): string
    {
        return DocumentoAssinaturaResource::getUrl('index', [
            'filters[status][values][0]' => StatusDocumentoAssinatura::Rascunho->value,
            'filters[status][values][1]' => StatusDocumentoAssinatura::Erro->value,
        ]);
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    /**
     * Envio com erro, ou rascunho parado há mais de alguns dias.
     */
    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)
            ->where(fn (Builder $consulta) => $consulta
                ->where('status', StatusDocumentoAssinatura::Erro->value)
                ->orWhere('created_at', '<=', $this->limiteDoRascunho()))
            ->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $limiteDoRascunho = $this->limiteDoRascunho();

        return $this->consulta($usuario)
            ->orderByRaw('case when status = ? then 0 else 1 end', [StatusDocumentoAssinatura::Erro->value])
            ->orderBy('created_at')
            ->limit($limite)
            ->get()
            ->map(function (DocumentoAssinatura $documento) use ($limiteDoRascunho): ItemPendencia {
                $comErro = $documento->status === StatusDocumentoAssinatura::Erro;

                return new ItemPendencia(
                    titulo: $documento->titulo,
                    detalhe: $comErro
                        ? 'O envio deu erro: abra para ver o motivo e enviar de novo'
                        : 'Montado em '.$documento->created_at->format('d/m/Y').' e ainda não enviado',
                    desde: $documento->created_at,
                    url: DocumentoAssinaturaResource::getUrl('view', ['record' => $documento]),
                    atrasado: $comErro || $documento->created_at->lte($limiteDoRascunho),
                );
            })
            ->all();
    }

    /**
     * @return Builder<DocumentoAssinatura>
     */
    private function consulta(User $usuario): Builder
    {
        $consulta = DocumentoAssinatura::query()->whereIn('status', [StatusDocumentoAssinatura::Rascunho->value, StatusDocumentoAssinatura::Erro->value]);

        return $usuario->can('View:ProcessoFuncionario') ? $consulta : $consulta->semRh();
    }

    private function limiteDoRascunho(): CarbonInterface
    {
        return now()->subDays((int) config('mesa_pendencias.rascunho_documento_dias', 2));
    }
}
