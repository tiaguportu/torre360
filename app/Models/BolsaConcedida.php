<?php

namespace App\Models;

use App\Enums\StatusBolsa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BolsaConcedida extends Model
{
    use HasFactory;

    protected $fillable = [
        'matricula_id',
        'tipo_bolsa_id',
        'percentual',
        'data_inicio',
        'data_fim',
        'status',
        'aprovado_por_user_id',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'status' => StatusBolsa::class,
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function tipoBolsa(): BelongsTo
    {
        return $this->belongsTo(TipoBolsa::class);
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por_user_id');
    }

    /**
     * Vigente hoje: aprovada, já começou e ainda não terminou (ou não tem fim definido).
     */
    public function isAtiva(): bool
    {
        if ($this->status !== StatusBolsa::Aprovada) {
            return false;
        }

        $hoje = now()->startOfDay();

        if ($this->data_inicio->gt($hoje)) {
            return false;
        }

        return $this->data_fim === null || $this->data_fim->gte($hoje);
    }

    /**
     * Percentual de bolsa vigente hoje para a matrícula (0 se não houver nenhuma ativa).
     * Quando há mais de uma bolsa ativa, soma os percentuais (limitado a 100).
     */
    public static function percentualAtivoPara(Matricula $matricula): int
    {
        $percentual = self::where('matricula_id', $matricula->id)
            ->where('status', StatusBolsa::Aprovada)
            ->get()
            ->filter(fn (self $bolsa) => $bolsa->isAtiva())
            ->sum('percentual');

        return (int) min(100, $percentual);
    }
}
