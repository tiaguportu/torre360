<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lead B2B captado pela landing page do produto (`/`): escolas que pedem
 * uma demonstração do Torre360. Não confundir com `Interessado`, que é a
 * família/aluno em processo de captação para matrícula.
 */
class LandingLead extends Model
{
    public const STATUS_NOVO = 'novo';

    public const STATUS_EM_CONTATO = 'em_contato';

    public const STATUS_DESCARTADO = 'descartado';

    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        self::STATUS_NOVO => 'Novo',
        self::STATUS_EM_CONTATO => 'Em contato',
        self::STATUS_DESCARTADO => 'Descartado',
    ];

    protected $fillable = ['nome', 'email', 'whatsapp', 'mensagem', 'status'];

    public function scopeNovos(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NOVO);
    }
}
