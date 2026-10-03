<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IndicacaoInteressado extends Model
{
    use HasFactory;

    protected $table = 'indicacao_interessados';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_MATRICULADO = 'matriculado';

    public const STATUS_RECOMPENSADO = 'recompensado';

    public const STATUS_CANCELADO = 'cancelado';

    protected $fillable = [
        'indicador_pessoa_id',
        'interessado_id',
        'codigo_indicacao',
        'status',
        'recompensa_tipo',
        'recompensa_detalhe',
        'valor_recompensa',
        'data_conversao',
        'data_recompensa',
        'recompensado_por_usuario_id',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_conversao' => 'datetime',
            'data_recompensa' => 'datetime',
            'valor_recompensa' => 'decimal:2',
        ];
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'indicador_pessoa_id');
    }

    public function quemIndicou(): BelongsTo
    {
        return $this->indicador();
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class, 'interessado_id');
    }

    public function recompensadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recompensado_por_usuario_id');
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDENTE);
    }

    public function scopeMatriculados(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_MATRICULADO);
    }

    public function scopeRecompensados(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RECOMPENSADO);
    }

    public function marcarMatriculado(): bool
    {
        return $this->update([
            'status' => self::STATUS_MATRICULADO,
            'data_conversao' => now(),
        ]);
    }

    public function marcarRecompensado(?User $usuario = null, ?string $detalhe = null, ?float $valor = null): bool
    {
        $dados = [
            'status' => self::STATUS_RECOMPENSADO,
            'data_recompensa' => now(),
            'recompensado_por_usuario_id' => $usuario?->id ?? auth()->id(),
        ];

        if ($detalhe !== null) {
            $dados['recompensa_detalhe'] = $detalhe;
        }

        if ($valor !== null) {
            $dados['valor_recompensa'] = $valor;
        }

        return $this->update($dados);
    }

    /**
     * Gera um código de indicação único e elegante para uma família.
     */
    public static function gerarCodigoParaPessoa(Pessoa $pessoa): string
    {
        $primeiroNome = Str::slug(explode(' ', trim($pessoa->nome))[0] ?? 'IND');
        $prefixo = strtoupper(substr($primeiroNome, 0, 4));
        if (strlen($prefixo) < 3) {
            $prefixo = 'FAM';
        }

        do {
            $sufixo = strtoupper(Str::random(4));
            $codigo = "{$prefixo}-{$sufixo}";
        } while (Pessoa::where('codigo_indicacao', $codigo)->exists() || self::where('codigo_indicacao', $codigo)->exists());

        return $codigo;
    }
}
