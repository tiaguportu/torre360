<?php

namespace App\Models;

use App\Services\ContadoresCrm;
use App\Services\CrmIaVendasService;
use App\Services\LeadFunilService;
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
        // As abas "Estagnados"/"Precisa de contato" (ContadoresCrm) também dependem do histórico.
        $descartarDossie = static function (self $historico): void {
            Cache::forget(CrmIaVendasService::chaveCacheDossie($historico->interessado_id));
            ContadoresCrm::invalidar();
        };

        static::saved($descartarDossie);
        static::deleted($descartarDossie);

        // O primeiro contato do lead (`data_primeiro_contato`) deriva do histórico: se o registro que o definia muda de data,
        // de tipo ou de autoria, ou é excluído, `data_primeiro_contato` acompanha. Criar já é tratado por quem cria
        // (LeadFunilService::registrarAtendimento/registrarPrimeiroContato), por isso só `updated` e `deleted`.
        static::updated(static function (self $historico): void {
            if ($historico->wasChanged(['data_contato', 'tipo_contato_interessado_id', 'automatico', 'usuario_id'])) {
                // Durante `updated` os valores originais ainda são os de antes da alteração.
                self::reavaliarPrimeiroContatoDoLead($historico, (new self)->setRawAttributes($historico->getRawOriginal(), true));
            }
        });
        static::deleted(static function (self $historico): void {
            self::reavaliarPrimeiroContatoDoLead($historico, $historico);
        });
    }

    private static function reavaliarPrimeiroContatoDoLead(self $atual, self $antes): void
    {
        $lead = Interessado::query()->find($atual->interessado_id);

        if ($lead !== null) {
            app(LeadFunilService::class)->reavaliarPrimeiroContato($lead, $antes);
        }
    }

    /**
     * Contatos que contam como o primeiro da escola com a família (`data_primeiro_contato`): registrados por uma pessoa
     * (`usuario_id`; ações da própria família não têm), não automáticos e fora dos tipos administrativos
     * (movimentação no funil, reenvio do formulário). Fonte única da regra para o backfill e a reavaliação.
     */
    public function scopeContatosDaEscola(Builder $query): Builder
    {
        return $query->interacoes()
            ->whereNotNull('usuario_id')
            ->whereNotIn(
                'tipo_contato_interessado_id',
                TipoContatoInteressado::query()->whereIn('nome', TipoContatoInteressado::ADMINISTRATIVOS)->select('id'),
            );
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
