<?php

namespace App\Models;

use App\Services\CrmIaVendasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Valor padrão também na instância recém-criada (o do banco só aparece após recarregar o registro).
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['automatico' => false];

    protected $fillable = ['interessado_id', 'usuario_id', 'tipo_contato_interessado_id', 'relato', 'data_contato', 'duracao_minutos', 'resultado', 'automatico'];

    protected function casts(): array
    {
        return [
            'data_contato' => 'datetime',
            'automatico' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // O dossiê da IA em cache (CrmIaVendasService::dossieDoLead) descreve o histórico do lead: qualquer
        // mudança nele o torna desatualizado.
        $descartarDossie = static function (self $historico): void {
            Cache::forget(CrmIaVendasService::chaveCacheDossie($historico->interessado_id));
        };

        static::saved($descartarDossie);
        static::deleted($descartarDossie);
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
