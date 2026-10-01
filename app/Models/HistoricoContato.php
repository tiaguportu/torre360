<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoContato extends Model
{
    protected $table = 'historico_contato';

    protected $fillable = ['interessado_id', 'usuario_id', 'tipo_contato_interessado_id', 'relato', 'data_contato', 'duracao_minutos', 'resultado'];

    protected function casts(): array
    {
        return [
            'data_contato' => 'datetime',
        ];
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }

    public function tipoContato(): BelongsTo
    {
        return $this->belongsTo(TipoContatoInteressado::class, 'tipo_contato_interessado_id');
    }

    /**
     * Usuário que registrou este contato no sistema.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
