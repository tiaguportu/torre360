<?php

namespace App\Models;

use App\Enums\StatusVisitaInteressado;
use Database\Factories\VisitaInteressadoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisitaInteressado extends Model
{
    /** @use HasFactory<VisitaInteressadoFactory> */
    use HasFactory;

    protected $table = 'visita_interessado';

    protected $fillable = ['interessado_id', 'interessado_dependente_id', 'usuario_id', 'data_hora', 'status', 'observacoes', 'lembrete_enviado_em'];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'lembrete_enviado_em' => 'datetime',
            'status' => StatusVisitaInteressado::class,
        ];
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }

    public function dependente(): BelongsTo
    {
        return $this->belongsTo(InteressadoDependente::class, 'interessado_dependente_id');
    }

    /**
     * Consultor responsável pela visita.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Pesquisa de satisfação pós-tour respondida pela família.
     */
    public function pesquisa(): HasOne
    {
        return $this->hasOne(PesquisaSatisfacaoVisita::class, 'visita_interessado_id');
    }

    /**
     * Retorna a pesquisa existente ou cria uma nova com token exclusivo para a visita.
     */
    public function obterOuCriarPesquisa(): PesquisaSatisfacaoVisita
    {
        if ($this->relationLoaded('pesquisa') && $this->pesquisa !== null) {
            return $this->pesquisa;
        }

        $pesquisa = $this->pesquisa()->first();
        if ($pesquisa) {
            $this->setRelation('pesquisa', $pesquisa);

            return $pesquisa;
        }

        $nova = $this->pesquisa()->create([
            'interessado_id' => $this->interessado_id,
            'token' => PesquisaSatisfacaoVisita::gerarToken(),
        ]);

        $this->setRelation('pesquisa', $nova);

        return $nova;
    }

    public function scopeAgendadas(Builder $query): Builder
    {
        return $query->where('status', StatusVisitaInteressado::Agendada);
    }

    /**
     * Visitas agendadas que ocorrem dentro da janela informada e ainda sem lembrete enviado.
     */
    public function scopePendentesDeLembrete(Builder $query, int $horas = 24): Builder
    {
        return $query->agendadas()
            ->whereNull('lembrete_enviado_em')
            ->whereBetween('data_hora', [now(), now()->addHours($horas)]);
    }

    public function estaAtrasada(): bool
    {
        return $this->status === StatusVisitaInteressado::Agendada && $this->data_hora->isPast();
    }
}
