<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoContatoInteressado extends Model
{
    protected $table = 'tipo_contato_interessado';

    /** Registros administrativos do funil (mover etapa, reativar, perder): não são uma conversa com a família. */
    public const FUNIL = 'Movimentação no Funil';

    /** Reenvio do formulário público por um lead que já existia. */
    public const FORMULARIO_SITE = 'Formulário do Site';

    /**
     * Tipos que o sistema grava por conta própria: não oferecidos ao registrar atendimento e que nunca contam
     * como o primeiro contato da escola com a família (`data_primeiro_contato`).
     *
     * @var list<string>
     */
    public const ADMINISTRATIVOS = [self::FUNIL, self::FORMULARIO_SITE];

    protected $fillable = ['nome'];

    /**
     * Tipo de contato pelo nome, criado se ainda não existir. Substitui a busca por `like '%Presencial%'`
     * (com `?? 1` de fallback) que fazia registros do sistema aparecerem como visita presencial.
     */
    public static function porNome(string $nome): self
    {
        return static::firstOrCreate(['nome' => $nome]);
    }
}
