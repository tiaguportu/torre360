<?php

namespace App\Models;

use App\Enums\StatusRsvp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoConfirmacao extends Model
{
    use HasFactory;

    protected $table = 'evento_confirmacoes';

    protected $fillable = [
        'evento_escolar_id',
        'matricula_id',
        'responsavel_id',
        'status',
        'quantidade_acompanhantes',
        'autorizado',
        'data_resposta',
        'ip_resposta',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusRsvp::class,
            'quantidade_acompanhantes' => 'integer',
            'autorizado' => 'boolean',
            'data_resposta' => 'datetime',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoEscolar::class, 'evento_escolar_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'responsavel_id');
    }
}
