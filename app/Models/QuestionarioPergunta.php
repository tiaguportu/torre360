<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionarioPergunta extends Model
{
    protected $fillable = [
        'questionario_bloco_id',
        'identificador',
        'enunciado',
        'tipo',
        'opcoes',
        'is_obrigatoria',
        'ordem',
        'condicao_exibicao',
    ];

    protected $casts = [
        'opcoes' => 'json',
        'is_obrigatoria' => 'boolean',
        'condicao_exibicao' => 'array',
    ];

    public function bloco(): BelongsTo
    {
        return $this->belongsTo(QuestionarioBloco::class, 'questionario_bloco_id');
    }

    /**
     * Avalia se esta pergunta deve ser exibida com base na condição configurada e nas respostas fornecidas.
     *
     * @param  array<string, mixed>  $respostas  Mapa de "pergunta_{id}" => valor_respondido
     */
    public function deveSerExibida(array $respostas): bool
    {
        if (empty($this->condicao_exibicao)) {
            return true;
        }

        $condicao = $this->condicao_exibicao;
        $perguntaId = $condicao['pergunta_id'] ?? null;
        $operador = $condicao['operador'] ?? 'igual';
        $valorEsperado = $condicao['valor'] ?? null;

        if (! $perguntaId) {
            return true;
        }

        $chave = "pergunta_{$perguntaId}";
        $valorRespondido = $respostas[$chave] ?? null;

        return match ($operador) {
            'igual' => $valorRespondido == $valorEsperado,
            'diferente' => $valorRespondido != $valorEsperado,
            'contem' => is_array($valorRespondido)
                ? in_array($valorEsperado, $valorRespondido)
                : str_contains((string) $valorRespondido, (string) $valorEsperado),
            'nao_contem' => is_array($valorRespondido)
                ? ! in_array($valorEsperado, $valorRespondido)
                : ! str_contains((string) $valorRespondido, (string) $valorEsperado),
            'preenchido' => ! empty($valorRespondido),
            'nao_preenchido' => empty($valorRespondido),
            'maior_que' => (float) $valorRespondido > (float) $valorEsperado,
            'menor_que' => (float) $valorRespondido < (float) $valorEsperado,
            default => true,
        };
    }
}
