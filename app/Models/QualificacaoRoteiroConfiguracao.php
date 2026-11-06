<?php

namespace App\Models;

use App\Support\Qualificacao\Roteiro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Personalização do roteiro de qualificação do lead (App\Support\Qualificacao\Roteiro), salva em "CRM / Comercial →
 * Roteiro de qualificação". Cada salvamento é uma linha e a mais recente vale; "Restaurar padrão" apaga todas.
 *
 * `valores`: `['campos' => [chave => ['pergunta' => string, 'ordem' => int, 'gate' => bool, 'ativo' => bool]]]`. Chave
 * ausente (ou campo novo, criado depois do salvamento) continua valendo o padrão do código.
 */
class QualificacaoRoteiroConfiguracao extends Model
{
    public const CACHE_KEY = 'qualificacao_roteiro_configuracao';

    protected $table = 'qualificacao_roteiro_configuracoes';

    protected $fillable = ['valores', 'atualizado_por'];

    protected function casts(): array
    {
        return [
            'valores' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::limparCache());
        static::deleted(fn () => self::limparCache());
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }

    /**
     * A personalização em vigor. Sem a tabela (antes da migration) ou sem nenhuma linha, devolve vazio e o roteiro usa os
     * padrões do código; uma falha de leitura nunca é guardada em cache.
     *
     * @return array<string, mixed>
     */
    public static function sobrescritas(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()->latest('id')->first()?->valores ?? []);
        } catch (Throwable) {
            return [];
        }
    }

    public static function limparCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Roteiro::esquecer();
    }
}
