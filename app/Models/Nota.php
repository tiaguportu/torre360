<?php

namespace App\Models;

use App\Enums\SituacaoNota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nota extends Model
{
    protected $table = 'nota';

    protected $fillable = ['avaliacao_id', 'matricula_id', 'valor', 'situacao'];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoNota::class,
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }
}
