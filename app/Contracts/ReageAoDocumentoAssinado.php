<?php

namespace App\Contracts;

use App\Models\DocumentoAssinatura;

/**
 * Registro que muda quando o documento ligado a ele termina de ser assinado (Onda 69): o cancelamento de matrícula guarda
 * o distrato assinado; o item de RH passa a feito com a cópia assinada como comprovante.
 */
interface ReageAoDocumentoAssinado
{
    public function documentoAssinado(DocumentoAssinatura $documento): void;
}
