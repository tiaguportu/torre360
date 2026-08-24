<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionarioPerguntaResposta extends Model
{
    protected $table = 'questionario_pergunta_respostas';

    protected $fillable = [
        'questionario_resposta_id',
        'questionario_pergunta_id',
        'resposta_texto',
        'resposta_json',
    ];

    protected function casts(): array
    {
        return [
            'resposta_json' => 'json',
        ];
    }

    public function respostaPai(): BelongsTo
    {
        return $this->belongsTo(QuestionarioResposta::class, 'questionario_resposta_id');
    }

    public function pergunta(): BelongsTo
    {
        return $this->belongsTo(QuestionarioPergunta::class, 'questionario_pergunta_id');
    }

    public function getValorExibicaoAttribute(): string
    {
        $pergunta = $this->pergunta;
        if (! $pergunta) {
            if ($this->resposta_texto !== null && $this->resposta_texto !== '') {
                return $this->resposta_texto;
            }
            if (! empty($this->resposta_json)) {
                return is_array($this->resposta_json) ? implode(', ', $this->resposta_json) : $this->resposta_json;
            }

            return '---';
        }

        $tipo = $pergunta->tipo;

        // Se for um dos tipos especiais e puder ter múltiplos (salvo em resposta_json)
        $valores = [];
        if (! empty($this->resposta_json) && is_array($this->resposta_json)) {
            $valores = $this->resposta_json;
        } elseif ($this->resposta_texto !== null && $this->resposta_texto !== '') {
            $valores = [$this->resposta_texto];
        }

        if (empty($valores)) {
            return '---';
        }

        if ($tipo === 'usuarios') {
            $nomes = User::whereIn('id', $valores)->pluck('name')->toArray();

            return ! empty($nomes) ? implode(', ', $nomes) : implode(', ', $valores);
        }

        if ($tipo === 'alunos_turma' || $tipo === 'pessoas') {
            $nomes = Pessoa::whereIn('id', $valores)->pluck('nome')->toArray();

            return ! empty($nomes) ? implode(', ', $nomes) : implode(', ', $valores);
        }

        // Caso geral
        if (! empty($this->resposta_json)) {
            return is_array($this->resposta_json) ? implode(', ', $this->resposta_json) : $this->resposta_json;
        }

        return $this->resposta_texto ?? '---';
    }
}
