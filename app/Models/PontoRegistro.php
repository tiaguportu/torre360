<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrigemRegistroPonto;
use App\Exceptions\PontoException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marcação de ponto. É APENAS INSERÇÃO: o model recusa alterar e apagar (e, onde o banco permite, gatilhos fazem o
 * mesmo). Cada registro carrega o NSR da unidade e o hash do anterior, de modo que qualquer adulteração ou remoção
 * quebra a cadeia e é detectada por `ponto:verificar-integridade`.
 */
class PontoRegistro extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'ponto_registros';

    protected $fillable = [
        'unidade_id',
        'funcionario_id',
        'nsr',
        'momento',
        'fuso',
        'origem',
        'ip',
        'hora_legal_ok',
        'desvio_segundos',
        'hash_anterior',
        'hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nsr' => 'integer',
            'momento' => 'datetime',
            'origem' => OrigemRegistroPonto::class,
            'hora_legal_ok' => 'boolean',
            'desvio_segundos' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new PontoException('Registro de ponto não pode ser alterado. Use um tratamento (ajuste) para corrigir.');
        });

        static::deleting(function (): never {
            throw new PontoException('Registro de ponto não pode ser apagado.');
        });
    }

    /**
     * Hash SHA-256 da marcação, encadeado ao anterior. A ordem e o formato dos campos fazem parte do contrato: mudar
     * qualquer um invalida a verificação de todos os registros já gravados.
     */
    public static function calcularHash(int $unidadeId, int $nsr, int $funcionarioId, string $momento, string $fuso, string $origem, ?string $hashAnterior): string
    {
        return hash('sha256', implode('|', [$unidadeId, $nsr, $funcionarioId, $momento, $fuso, $origem, $hashAnterior ?? '']));
    }

    public function hashConfere(): bool
    {
        return hash_equals($this->hash, self::calcularHash(
            (int) $this->unidade_id,
            (int) $this->nsr,
            (int) $this->funcionario_id,
            $this->momento->format('Y-m-d H:i:s'),
            (string) $this->fuso,
            $this->origem->value,
            $this->hash_anterior,
        ));
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }
}
