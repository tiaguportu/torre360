<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Alergeno;
use App\Enums\TipoRefeicaoCardapio;
use Carbon\CarbonInterface;
use Database\Factories\CardapioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Uma refeição do cardápio numa data (ex.: almoço de 22/10), com os alérgenos declarados. Fica visível no Portal da
 * Família só depois de publicada. `unidade_id` nulo vale para todas as unidades.
 */
class Cardapio extends Model
{
    /** @use HasFactory<CardapioFactory> */
    use HasFactory;

    use LogsActivity;

    protected $table = 'cardapios';

    protected $fillable = [
        'data',
        'tipo_refeicao',
        'descricao',
        'alergenos',
        'unidade_id',
        'publicado',
        'observacoes',
        'criado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'tipo_refeicao' => TipoRefeicaoCardapio::class,
            'alergenos' => 'array',
            'publicado' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Cardapio $cardapio): void {
            $cardapio->unidade_chave = (int) ($cardapio->unidade_id ?? 0);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['data', 'tipo_refeicao', 'descricao', 'alergenos', 'unidade_id', 'publicado'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('cardapio')
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Refeição incluída no cardápio.',
                'updated' => 'Refeição do cardápio atualizada.',
                'deleted' => 'Refeição removida do cardápio.',
                default => "Cardápio: {$evento}",
            });
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    /**
     * @return list<Alergeno>
     */
    public function alergenosEnum(): array
    {
        return collect($this->alergenos ?? [])
            ->map(fn (mixed $valor): ?Alergeno => Alergeno::tryFrom((string) $valor))
            ->filter()
            ->values()
            ->all();
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('publicado', true);
    }

    /**
     * Refeições da semana (segunda a domingo) que contém a data.
     */
    public function scopeDaSemana(Builder $query, CarbonInterface $dia): Builder
    {
        return $query
            ->whereDate('data', '>=', $dia->copy()->startOfWeek()->toDateString())
            ->whereDate('data', '<=', $dia->copy()->endOfWeek()->toDateString());
    }

    /**
     * Cardápios que valem para a unidade: os dela e os gerais (sem unidade).
     */
    public function scopeDaUnidade(Builder $query, ?int $unidadeId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('unidade_id')->when($unidadeId, fn (Builder $qq) => $qq->orWhere('unidade_id', $unidadeId)));
    }
}
