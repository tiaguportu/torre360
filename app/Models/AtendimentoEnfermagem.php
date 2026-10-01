<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtendimentoEnfermagem extends Model
{
    use HasFactory;

    protected $table = 'atendimento_enfermagems';

    protected $fillable = ['pessoa_id', 'atendido_por_user_id', 'data_hora', 'sintomas_queixa', 'procedimento_realizado', 'medicamento_ministrado', 'notificado_responsaveis', 'observacoes'];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'notificado_responsaveis' => 'boolean',
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por_user_id');
    }
}
