<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Sobrescritas dos parâmetros do Risco de Evasão (config/risco_evasao.php).
 * Guarda uma única linha; chaves ausentes continuam valendo o padrão do arquivo.
 */
class RiscoEvasaoConfiguracao extends Model
{
    public const CACHE_KEY = 'risco_evasao_configuracao';

    protected $table = 'risco_evasao_configuracoes';

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
     * Aplica as sobrescritas sobre a configuração em memória.
     */
    public static function aplicar(): void
    {
        $sobrescritas = self::sobrescritas();

        if ($sobrescritas !== []) {
            $config = config('risco_evasao');

            // 'pesos' e 'desempenho' são mapas chave => pontos: mescla por chave, para que
            // um fator novo (ausente em personalizações antigas) mantenha o padrão. 'frequencia'
            // é uma lista de faixas e é substituída por inteiro quando sobrescrita.
            foreach (['pesos', 'desempenho'] as $chave) {
                if (isset($sobrescritas[$chave])) {
                    $sobrescritas[$chave] = array_replace($config[$chave] ?? [], $sobrescritas[$chave]);
                }
            }

            config(['risco_evasao' => array_replace($config, $sobrescritas)]);
        }
    }

    public static function limparCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
