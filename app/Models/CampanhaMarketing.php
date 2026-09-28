<?php

namespace App\Models;

use Database\Factories\CampanhaMarketingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampanhaMarketing extends Model
{
    /** @use HasFactory<CampanhaMarketingFactory> */
    use HasFactory;

    protected $table = 'campanha_marketing';

    protected $guarded = [];

    /**
     * Canais de aquisição disponíveis para classificar uma campanha.
     *
     * @var array<string, string>
     */
    public const CANAIS = [
        'google_ads' => 'Google Ads',
        'meta_ads' => 'Meta Ads (Facebook/Instagram)',
        'instagram' => 'Instagram orgânico',
        'email' => 'E-mail marketing',
        'whatsapp' => 'WhatsApp',
        'indicacao' => 'Indicação',
        'evento' => 'Evento / Feira',
        'outdoor' => 'Mídia offline',
        'outro' => 'Outro',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'custo' => 'decimal:2',
            'ativa' => 'boolean',
        ];
    }

    /**
     * O código UTM é sempre guardado em minúsculas e sem espaços, pois o
     * `UtmTracker` normaliza o `utm_campaign` recebido do mesmo jeito.
     */
    protected function codigoUtm(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? mb_strtolower(trim($value)) : null,
        );
    }

    public function interessados(): HasMany
    {
        return $this->hasMany(Interessado::class);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativa', true);
    }

    /**
     * Localiza a campanha ativa cujo código UTM corresponde ao valor de utm_campaign.
     */
    public static function porCodigoUtm(?string $codigo): ?self
    {
        if (blank($codigo)) {
            return null;
        }

        return static::ativas()
            ->where('codigo_utm', mb_strtolower(trim($codigo)))
            ->first();
    }

    public function getCanalLabelAttribute(): ?string
    {
        return self::CANAIS[$this->canal] ?? $this->canal;
    }
}
