<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrigemLancamentoBancoHoras;
use App\Exceptions\PontoException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Razão do banco de horas: cada linha é um crédito (positivo) ou débito (negativo) em minutos. APENAS INSERÇÃO; um erro
 * é corrigido com um novo lançamento de ajuste, e o saldo é sempre a soma das linhas.
 */
class PontoBancoHorasLancamento extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'ponto_banco_horas_lancamentos';

    protected $fillable = [
        'funcionario_id',
        'data',
        'minutos',
        'origem',
        'vence_em',
        'credito_origem_id',
        'observacao',
        'criado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'minutos' => 'integer',
            'origem' => OrigemLancamentoBancoHoras::class,
            'vence_em' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new PontoException('Lançamento do banco de horas não pode ser alterado. Faça um lançamento de ajuste.');
        });

        static::deleting(function (): never {
            throw new PontoException('Lançamento do banco de horas não pode ser apagado.');
        });
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }
}
