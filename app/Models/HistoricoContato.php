<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoContato extends Model
{
    /**
     * Resultado do contato, como gravado no banco => rótulo exibido.
     *
     * @var array<string, string>
     */
    public const RESULTADOS = [
        'agendou_visita' => 'Agendou Visita',
        'retornar' => 'Retornar depois',
        'sem_interesse' => 'Sem Interesse',
        'matriculou' => 'Efetuou Matrícula',
        'outro' => 'Outro',
    ];

    protected $table = 'historico_contato';

    protected $fillable = ['interessado_id', 'usuario_id', 'tipo_contato_interessado_id', 'relato', 'data_contato', 'duracao_minutos', 'resultado', 'automatico'];

    protected function casts(): array
    {
        return [
            'data_contato' => 'datetime',
            'automatico' => 'boolean',
        ];
    }

    /**
     * Contatos que contam como interação com a família: exclui o que o próprio sistema gerou
     * (e-mails da régua, análises de IA), que registra atividade mas não é conversa nem acompanhamento.
     */
    public function scopeInteracoes(Builder $query): Builder
    {
        return $query->where('automatico', false);
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
