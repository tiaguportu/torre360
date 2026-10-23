<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\NivelAssinatura;
use App\Models\DocumentoAssinatura;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Documento oficial da escola (declaração, histórico escolar) que pode ganhar uma cópia assinada digitalmente (Onda 74).
 * Guarda o caminho da cópia, quando foi assinada e com que nível; o PDF e o resultado da leitura ficam no
 * {@see DocumentoAssinatura} de que a cópia veio.
 *
 * Quem usa a trait implementa `ReageAoDocumentoAssinado` com {@see self::documentoAssinado()}.
 */
trait TemCopiaAssinada
{
    public function initializeTemCopiaAssinada(): void
    {
        $this->mergeFillable(['arquivo_assinado_path', 'assinado_em', 'assinatura_nivel']);
        $this->mergeCasts(['assinado_em' => 'datetime', 'assinatura_nivel' => NivelAssinatura::class]);
    }

    /**
     * @return MorphMany<DocumentoAssinatura, $this>
     */
    public function documentosAssinatura(): MorphMany
    {
        return $this->morphMany(DocumentoAssinatura::class, 'documentavel')->orderByDesc('id');
    }

    public function temCopiaAssinada(): bool
    {
        return filled($this->arquivo_assinado_path);
    }

    public function documentoAssinado(DocumentoAssinatura $documento): void
    {
        if (blank($documento->arquivo_assinado_path)) {
            return;
        }

        $this->forceFill([
            'arquivo_assinado_path' => $documento->arquivo_assinado_path,
            'assinado_em' => $documento->concluido_em ?? now(),
            'assinatura_nivel' => $documento->nivel_assinatura ?? NivelAssinatura::Eletronica,
        ])->save();
    }
}
