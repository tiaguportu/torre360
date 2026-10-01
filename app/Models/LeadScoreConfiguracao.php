<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Sobrescritas dos parâmetros do Lead Score (config/lead_score.php).
 * Guarda uma única linha; chaves ausentes continuam valendo o padrão do arquivo.
 */
class LeadScoreConfiguracao extends Model
{
    public const CACHE_KEY = 'lead_score_configuracao';

    protected $table = 'lead_score_configuracao';

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
            $config = config('lead_score');

            // Pesos e tabelas por chave: fatores novos (ausentes em personalizações antigas) mantêm o padrão.
            foreach (['pesos', 'percepcao_consultor'] as $chave) {
                if (isset($sobrescritas[$chave])) {
                    $sobrescritas[$chave] = array_replace($config[$chave] ?? [], $sobrescritas[$chave]);
                }
            }

            config(['lead_score' => array_replace($config, $sobrescritas)]);
        }
    }

    public static function limparCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
