<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicamentoAluno extends Model
{
    use HasFactory;

    protected $table = 'medicamento_alunos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'autorizado_responsaveis' => 'boolean',
        ];
    }

    public function fichaMedica(): BelongsTo
    {
        return $this->belongsTo(FichaMedica::class, 'ficha_medica_id');
    }
}
