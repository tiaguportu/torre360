<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico;

final readonly class PlanoAulaGeradoOutput
{
    /**
     * @param  list<array{descricao: string, habilidade_codigo: string, nivel_bloom: string}>  $objetivos
     * @param  array{nome: string, justificativa: string}  $metodologiaAtiva
     * @param  list<string>  $recursos
     * @param  list<array{etapa: string, minutos: int, atividade: string, papel_professor: string, papel_aluno: string}>  $cronograma
     * @param  list<array{criterio: string, instrumento: string, nivel_bloom: string}>  $avaliacaoFormativa
     * @param  list<int>  $habilidadeIds
     * @param  list<string>  $avisos
     */
    public function __construct(
        public array $objetivos,
        public array $metodologiaAtiva,
        public array $recursos,
        public array $cronograma,
        public array $avaliacaoFormativa,
        public ?string $observacoes,
        public array $habilidadeIds,
        public int $duracaoTotalPlanejadaMinutos,
        public array $avisos = [],
        public string $modeloIa = 'gemini-2.5-flash',
    ) {}

    /**
     * Formata os objetivos pedagógicos para o campo 'objetivos' do formulário.
     */
    public function toObjetivosTexto(): string
    {
        $linhas = [];
        foreach ($this->objetivos as $i => $obj) {
            $num = $i + 1;
            $linhas[] = "{$num}. [{$obj['habilidade_codigo']}] ({$obj['nivel_bloom']}): {$obj['descricao']}";
        }

        return implode("\n", $linhas);
    }

    /**
     * Formata metodologia ativa e cronograma detalhado para o campo 'metodologia'.
     */
    public function toMetodologiaTexto(): string
    {
        $linhas = [];
        $linhas[] = "METODOLOGIA ATIVA: {$this->metodologiaAtiva['nome']}";
        $linhas[] = "Justificativa: {$this->metodologiaAtiva['justificativa']}\n";
        $linhas[] = 'CRONOGRAMA DETALHADO DA AULA:';

        foreach ($this->cronograma as $etapa) {
            $linhas[] = "• {$etapa['etapa']} ({$etapa['minutos']} min): {$etapa['atividade']}";
            $linhas[] = "  - Professor: {$etapa['papel_professor']}";
            $linhas[] = "  - Estudantes: {$etapa['papel_aluno']}";
        }

        return implode("\n", $linhas);
    }

    /**
     * Formata os recursos didáticos para o campo 'recursos'.
     */
    public function toRecursosTexto(): string
    {
        return implode("\n", array_map(fn ($r): string => "• {$r}", $this->recursos));
    }

    /**
     * Formata a avaliação formativa para o campo 'avaliacao'.
     */
    public function toAvaliacaoTexto(): string
    {
        $linhas = [];
        $linhas[] = 'AVALIAÇÃO FORMATIVA:';
        foreach ($this->avaliacaoFormativa as $av) {
            $linhas[] = "• Instrumento: {$av['instrumento']} (Nível: {$av['nivel_bloom']})";
            $linhas[] = "  Critério: {$av['criterio']}";
        }

        if (! empty($this->observacoes)) {
            $linhas[] = "\nObservações: {$this->observacoes}";
        }

        return implode("\n", $linhas);
    }
}
