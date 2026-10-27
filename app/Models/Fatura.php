<?php

namespace App\Models;

use App\Enums\StatusFatura;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Fatura extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'contrato_id',
                'vencimento',
                'status',
                'gateway',
                'gateway_id',
                'status_gateway',
                'linha_digitavel',
                'pix_copia_e_cola',
                'boleto_url',
                'link_pagamento',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'faturas';

    protected $fillable = [
        'contrato_id',
        'vencimento',
        'status',
        'pix_copia_e_cola',
        'gateway',
        'gateway_id',
        'status_gateway',
        'linha_digitavel',
        'boleto_url',
        'link_pagamento',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusFatura::class,
            'vencimento' => 'date',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemFatura::class);
    }

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class);
    }

    public function cobrancaLogs(): HasMany
    {
        return $this->hasMany(ReguaCobrancaLog::class);
    }

    /**
     * Faturas em atraso: marcadas como Atrasado ou ainda Pendente/Parcial com o vencimento já passado. Nenhuma rotina
     * troca o status para Atrasado sozinha, então só o status não basta (mesma regra de `Matricula::hasDebitosVencidos()`).
     * Não considera fim de semana nem carência: para o valor devido, use `EncargosAtrasoService`.
     *
     * @param  Builder<Fatura>  $query
     * @return Builder<Fatura>
     */
    public function scopeEmAtraso(Builder $query, ?CarbonInterface $em = null): Builder
    {
        $hoje = ($em ?? today())->toDateString();

        return $query->where(function (Builder $q) use ($hoje): void {
            $q->where('status', StatusFatura::Atrasado->value)
                ->orWhere(fn (Builder $p) => $p
                    ->whereIn('status', [StatusFatura::Pendente->value, StatusFatura::Parcial->value])
                    ->whereDate('vencimento', '<', $hoje));
        });
    }

    /**
     * Valor total bruto da fatura (soma dos itens sem descontos)
     */
    protected function valorBruto(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->itens->sum(fn ($item) => $item->valor_unitario * $item->quantidade)
        );
    }

    /**
     * Valor total bruto da fatura (soma dos itens com seus respectivos descontos)
     */
    protected function valor(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->itens->reduce(function ($carry, $item) {
                    $totalItem = $item->valor_unitario * $item->quantidade;

                    if ($item->tipo_desconto === 'absoluto') {
                        $totalItem -= $item->desconto;
                    } else {
                        $totalItem -= ($totalItem * ($item->desconto / 100));
                    }

                    return $carry + $totalItem;
                }, 0);
            }
        );
    }

    /**
     * Valor total já pago (soma das transações bancárias vinculadas)
     */
    protected function valorPago(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->transacoes->where('tipo', 'entrada')->sum('valor')
        );
    }

    /**
     * Valor que ainda resta ser pago
     */
    protected function valorRestante(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->valor - $this->valor_pago
        );
    }
}
