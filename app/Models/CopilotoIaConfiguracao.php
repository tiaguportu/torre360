<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Sobrescritas do comportamento do Copiloto WhatsApp IA (config/copiloto_ia.php).
 * Guarda uma única linha; chaves ausentes continuam valendo o padrão do arquivo.
 */
class CopilotoIaConfiguracao extends Model
{
    public const CACHE_KEY = 'copiloto_ia_configuracao';

    protected $table = 'copiloto_ia_configuracoes';

    protected $fillable = ['valores', 'atualizado_por'];

    protected function casts(): array
    {
        return [
            'valores' => 'array',
        ];
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }

    /**
     * @return array<string, mixed>
     */
    public static function sobrescritas(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function (): array {
                if (! Schema::hasTable((new self)->getTable())) {
                    return [];
                }

                return self::query()->latest('id')->first()?->valores ?? [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Configuração efetiva: padrões do arquivo mesclados com as sobrescritas salvas.
     *
     * @return array<string, mixed>
     */
    public static function valores(): array
    {
        return self::mesclar(config('copiloto_ia'), self::sobrescritas());
    }

    /**
     * @param  array<string, mixed>  $padrao
     * @param  array<string, mixed>  $sobrescritas
     * @return array<string, mixed>
     */
    public static function mesclar(array $padrao, array $sobrescritas): array
    {
        // 'objetivos', 'tons' e 'gemini' são mapas chave => valor: mescla por chave, para que um
        // item novo (ausente em personalizações antigas) mantenha o padrão. 'diretrizes' é uma lista
        // e é substituída por inteiro quando sobrescrita.
        foreach (['objetivos', 'tons', 'gemini'] as $chave) {
            if (isset($sobrescritas[$chave]) && is_array($sobrescritas[$chave])) {
                $sobrescritas[$chave] = array_replace($padrao[$chave] ?? [], $sobrescritas[$chave]);
            }
        }

        return array_replace($padrao, $sobrescritas);
    }

    public static function limparCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
