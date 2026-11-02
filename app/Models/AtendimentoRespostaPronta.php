<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Texto pronto que o atendente escolhe ao responder (Onda 83). Sem setor, vale para todos. O texto aceita
 * `{{ SOLICITANTE }}`, `{{ ALUNO }}`, `{{ PROTOCOLO }}` e `{{ ESCOLA }}` e a pessoa revisa antes de enviar.
 */
class AtendimentoRespostaPronta extends Model
{
    protected $table = 'atendimento_respostas_prontas';

    protected $fillable = ['setor_id', 'titulo', 'texto', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(AtendimentoSetor::class, 'setor_id');
    }

    /**
     * Respostas do setor e as gerais, só as ativas.
     *
     * @param  Builder<self>  $consulta
     * @return Builder<self>
     */
    public function scopeDoSetor(Builder $consulta, ?int $setorId): Builder
    {
        return $consulta->where('ativo', true)
            ->where(fn (Builder $q) => $q->whereNull('setor_id')->orWhere('setor_id', $setorId))
            ->orderBy('titulo');
    }

    /**
     * Preenche as variáveis com os dados do chamado.
     */
    public function textoPara(AtendimentoChamado $chamado): string
    {
        $chamado->loadMissing(['solicitante', 'matricula.pessoa']);

        return strtr($this->texto, [
            '{{ SOLICITANTE }}' => $chamado->solicitante?->nome ?? 'responsável',
            '{{ ALUNO }}' => $chamado->matricula?->pessoa?->nome ?? 'o(a) estudante',
            '{{ PROTOCOLO }}' => $chamado->protocolo,
            '{{ ESCOLA }}' => (string) config('app.name'),
        ]);
    }
}
