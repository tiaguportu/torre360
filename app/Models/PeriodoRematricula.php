<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoRematricula extends Model
{
    use HasFactory;

    protected $table = 'periodo_rematriculas';

    protected $fillable = ['nome', 'periodo_letivo_origem_id', 'periodo_letivo_destino_id', 'template_contrato_id', 'data_inicio', 'data_fim', 'is_ativo', 'mensagem_orientacao', 'valor_taxa', 'quantidade_parcelas_padrao', 'valor_entrada_padrao'];

    public function periodoLetivoOrigem(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class, 'periodo_letivo_origem_id');
    }

    public function periodoLetivoDestino(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class, 'periodo_letivo_destino_id');
    }

    public function templateContrato(): BelongsTo
    {
        return $this->belongsTo(TemplateContrato::class);
    }

    public function rematriculas(): HasMany
    {
        return $this->hasMany(Rematricula::class);
    }

    /**
     * Preços por série definidos por um reajuste aplicado (Onda 44).
     */
    public function precos(): HasMany
    {
        return $this->hasMany(PeriodoRematriculaPreco::class);
    }

    /**
     * Valor total do novo contrato para a série: o preço definido por reajuste aplicado, ou o `valor_taxa` da campanha
     * quando a série não tem preço próprio (comportamento anterior, mantido para campanhas sem reajuste).
     */
    public function valorTotalParaSerie(?int $serieId): float
    {
        if ($serieId !== null) {
            $preco = $this->precos()->where('serie_id', $serieId)->value('valor_total');

            if ($preco !== null) {
                return (float) $preco;
            }
        }

        return (float) ($this->valor_taxa ?? 0);
    }

    public function isAberto(): bool
    {
        if (! $this->is_ativo) {
            return false;
        }

        $hoje = Carbon::today();

        return $hoje->betweenIncluded($this->data_inicio, $this->data_fim);
    }

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'is_ativo' => 'boolean',
            'valor_taxa' => 'decimal:2',
            'quantidade_parcelas_padrao' => 'integer',
            'valor_entrada_padrao' => 'decimal:2',
        ];
    }
}
