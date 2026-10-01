<?php

namespace App\Models;

use App\Enums\StatusRematricula;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rematricula extends Model
{
    use HasFactory;

    protected $table = 'rematriculas';

    protected $fillable = ['periodo_rematricula_id', 'matricula_origem_id', 'turma_destino_id', 'serie_destino_id', 'turno_pretendido_id', 'solicitante_user_id', 'status', 'contrato_id', 'nova_matricula_id', 'observacoes', 'data_confirmacao'];

    public function periodoRematricula(): BelongsTo
    {
        return $this->belongsTo(PeriodoRematricula::class);
    }

    public function matriculaOrigem(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_origem_id');
    }

    public function turmaDestino(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_destino_id');
    }

    public function serieDestino(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_destino_id');
    }

    public function turnoPretendido(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_pretendido_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function novaMatricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'nova_matricula_id');
    }

    protected function casts(): array
    {
        return [
            'status' => StatusRematricula::class,
            'data_confirmacao' => 'datetime',
        ];
    }
}
