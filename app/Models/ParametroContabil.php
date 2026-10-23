<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contas padrão da exportação contábil (Onda 73). Uma linha só: as contas de clientes e de fornecedores do escritório e as
 * contas de receita e de despesa que valem para o movimento ainda sem classificação no plano de contas da escola.
 */
class ParametroContabil extends Model
{
    protected $table = 'parametros_contabeis';

    protected $fillable = ['conta_clientes', 'conta_fornecedores', 'plano_conta_receita_id', 'plano_conta_despesa_id', 'atualizado_por_user_id'];

    public function planoContaReceita(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class, 'plano_conta_receita_id');
    }

    public function planoContaDespesa(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class, 'plano_conta_despesa_id');
    }

    public static function atual(): self
    {
        return self::query()->with(['planoContaReceita', 'planoContaDespesa'])->latest('id')->first() ?? new self;
    }
}
