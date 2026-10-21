<?php

declare(strict_types=1);

namespace App\Services\Ponto;

/**
 * Assinatura eletrônica do comprovante e dos arquivos do ponto. O REP-P exige assinatura com certificado ICP-Brasil
 * (Portaria MTP 671/2021, art. 79); uma implementação real precisa do certificado da empresa e é ligada em
 * `config('ponto.assinador')`.
 */
interface AssinadorPonto
{
    public function nome(): string;

    /**
     * Esta implementação produz uma assinatura ICP-Brasil válida? (Usada pela conformidade do REP-P.)
     */
    public function assina(): bool;

    /**
     * Assina o conteúdo canônico e devolve a assinatura (por exemplo, em base64), ou `null` se não assina.
     */
    public function assinar(string $conteudo): ?string;
}
