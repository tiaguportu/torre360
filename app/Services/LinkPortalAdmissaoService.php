<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;

/**
 * Ciclo de vida do link do Portal de Admissão enviado à família.
 *
 * O link vale no máximo {@see Interessado::DIAS_VALIDADE_TOKEN_DOCUMENTOS} dias (renovado quando a equipe gera, copia
 * ou envia) e pode ser revogado na hora: "Gerar novo link" troca o token do portal — a URL antiga passa a responder
 * "link expirado" — e invalida o convite legado.
 */
class LinkPortalAdmissaoService
{
    /**
     * Revoga o link atual e devolve a URL de um novo. Registra na linha do tempo quem fez e quando.
     */
    public function gerarNovo(Interessado $interessado, ?int $usuarioId = null): string
    {
        $tinhaLink = filled($interessado->token_documentos) || filled($interessado->token_convite);

        $token = $interessado->rotacionarTokenDocumentos();

        $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'Portal de Admissão']);

        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'usuario_id' => $usuarioId,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => $tinhaLink
                ? '🔒 Link do Portal de Admissão revogado e substituído por um novo (o anterior deixou de funcionar). Validade: '.Interessado::DIAS_VALIDADE_TOKEN_DOCUMENTOS.' dias.'
                : '🔗 Link do Portal de Admissão gerado. Validade: '.Interessado::DIAS_VALIDADE_TOKEN_DOCUMENTOS.' dias.',
            'data_contato' => now(),
            'automatico' => true,
        ]);

        return route('candidato.documentos.show', ['token' => $token]);
    }
}
