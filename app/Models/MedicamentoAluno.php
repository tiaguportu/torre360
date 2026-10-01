<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicamentoAluno extends Model
{
    use HasFactory;

    protected $table = 'medicamento_alunos';

    protected $fillable = ['ficha_medica_id', 'nome_medicamento', 'dosagem', 'horario_administracao', 'instrucoes', 'autorizado_responsaveis', 'arquivo_receita_path'];

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
