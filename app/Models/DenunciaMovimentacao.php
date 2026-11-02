<?php

namespace App\Models;

use App\Enums\TipoMovimentacaoDenuncia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DenunciaMovimentacao extends Model
{
    protected $table = 'denuncia_movimentacoes';

    protected $fillable = ['denuncia_id', 'user_id', 'tipo', 'texto', 'visivel_ao_autor'];

    /** Sempre com quem registrou: o app proíbe carga preguiçosa e o histórico é lido inteiro. */
    protected $with = ['user'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentacaoDenuncia::class,
            'texto' => 'encrypted',
            'visivel_ao_autor' => 'boolean',
        ];
    }

    public function denuncia(): BelongsTo
    {
        return $this->belongsTo(Denuncia::class, 'denuncia_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
