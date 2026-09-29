<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReguaCobranca extends Model
{
    use HasFactory;

    protected $table = 'regua_cobrancas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_ativo' => 'boolean',
            'dias_offset' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ReguaCobrancaLog::class);
    }

    /**
     * Calcula qual data de vencimento de fatura esta regra deve buscar hoje.
     * Exemplo: se dias_offset = -5, busca faturas que vencem daqui a 5 dias ($hoje + 5 dias).
     * Exemplo: se dias_offset = +3, busca faturas que venceram há 3 dias ($hoje - 3 dias).
     */
    public function calcularDataVencimentoAlvo(Carbon $dataReferencia): Carbon
    {
        return $dataReferencia->copy()->subDays($this->dias_offset);
    }

    public function isPreventivo(): bool
    {
        return $this->dias_offset < 0;
    }

    public function isNoVencimento(): bool
    {
        return $this->dias_offset === 0;
    }

    public function isAtraso(): bool
    {
        return $this->dias_offset > 0;
    }

    public function getGatilhoBadgeColorAttribute(): string
    {
        if ($this->isPreventivo()) {
            return 'info';
        }
        if ($this->isNoVencimento()) {
            return 'warning';
        }

        return 'danger';
    }

    public function getGatilhoDescricaoAttribute(): string
    {
        if ($this->isPreventivo()) {
            $dias = abs($this->dias_offset);

            return "{$dias} dia(s) antes do vencimento";
        }
        if ($this->isNoVencimento()) {
            return 'No dia do vencimento';
        }

        return "{$this->dias_offset} dia(s) após o vencimento (atraso)";
    }
}
