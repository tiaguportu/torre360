<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Par de cadastros de pessoa que a secretaria confirmou serem pessoas diferentes: o detector de duplicados não o sugere de novo.
 * O par fica sempre em ordem (`pessoa_menor_id` < `pessoa_maior_id`), ver `PessoaDuplicadaDetectorService::marcarComoDiferentes()`.
 */
class PessoaNaoDuplicada extends Model
{
    protected $table = 'pessoa_nao_duplicada';

    protected $fillable = ['pessoa_menor_id', 'pessoa_maior_id', 'user_id'];

    public function menor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_menor_id');
    }

    public function maior(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_maior_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
