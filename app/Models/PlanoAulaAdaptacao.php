<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusAdaptacaoPei;
use Database\Factories\PlanoAulaAdaptacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanoAulaAdaptacao extends Model
{
    /** @use HasFactory<PlanoAulaAdaptacaoFactory> */
    use HasFactory;

    protected $table = 'plano_aula_adaptacoes';

    protected $fillable = [
        'plano_aula_id',
        'matricula_id',
        'conteudo',
        'status',
        'gerado_por_user_id',
        'revisado_por_user_id',
        'revisado_em',
        'aprovado_por_user_id',
        'aprovado_em',
        'modelo_ia',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conteudo' => 'encrypted:array',
            'status' => StatusAdaptacaoPei::class,
            'revisado_em' => 'datetime',
            'aprovado_em' => 'datetime',
        ];
    }

    public function planoAula(): BelongsTo
    {
        return $this->belongsTo(PlanoAula::class, 'plano_aula_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function geradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerado_por_user_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_user_id');
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por_user_id');
    }
}
