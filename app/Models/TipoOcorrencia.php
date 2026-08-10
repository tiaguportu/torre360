<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoOcorrencia extends Model
{
    use HasFactory;

    protected $table = 'tipo_ocorrencias';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'notificar_responsaveis_padrao' => 'boolean',
        ];
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(OcorrenciaEscolar::class, 'tipo_ocorrencia_id');
    }
}
