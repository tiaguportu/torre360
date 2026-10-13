<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusOmrCorrecao;
use Database\Factories\OmrCorrecaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrCorrecao extends Model
{
    /** @use HasFactory<OmrCorrecaoFactory> */
    use HasFactory;

    protected $table = 'omr_correcoes';

    protected $fillable = [
        'avaliacao_id',
        'gabarito_folha_id',
        'matricula_id',
        'user_id',
        'arquivo_path',
        'arquivo_sha256',
        'status',
        'respostas',
        'respostas_finais',
        'alertas',
        'acertos',
        'total_validas',
        'nota_calculada',
        'motor',
        'layout_versao',
        'tempo_ms',
        'confirmada_por_user_id',
        'confirmada_em',
        'mensagem_erro',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusOmrCorrecao::class,
            'respostas' => 'array',
            'respostas_finais' => 'array',
            'alertas' => 'array',
            'acertos' => 'integer',
            'total_validas' => 'integer',
            'nota_calculada' => 'decimal:2',
            'layout_versao' => 'integer',
            'tempo_ms' => 'integer',
            'confirmada_em' => 'datetime',
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class);
    }

    public function gabaritoFolha(): BelongsTo
    {
        return $this->belongsTo(GabaritoFolha::class, 'gabarito_folha_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function confirmadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmada_por_user_id');
    }

    public function habilidades(): HasMany
    {
        return $this->hasMany(OmrCorrecaoHabilidade::class, 'omr_correcao_id');
    }

    public function estaConfirmada(): bool
    {
        return $this->status === StatusOmrCorrecao::Confirmada;
    }
}
