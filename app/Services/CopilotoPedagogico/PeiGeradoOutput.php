<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico;

final readonly class PeiGeradoOutput
{
    /**
     * @param  array{
     *     instrucoes_passo_a_passo: string,
     *     apoio_visual: string,
     *     tempo_estendido: string,
     *     recursos_acessibilidade: string,
     *     organizacao_ambiente: string
     * }  $adaptacoesPraticas
     */
    public function __construct(
        public string $barreiras,
        public array $adaptacoesPraticas,
        public string $adaptacaoAvaliacao,
        public string $estrategiasProfessor,
        public string $indicadoresAcompanhamento,
        public int $prazoRevisaoDias,
        public string $habilidadeBnccAlvo,
        public ?string $profundidadeRequerValidacaoAee = null,
        public string $avisoLegal = 'Sugestão pedagógica gerada por Inteligência Artificial conforme a Lei nº 13.146/2015 (LBI) e princípios do DUA. Não substitui laudo médico, parecer clínico ou planejamento oficial do AEE. Requer validação e adaptação pelo docente responsável.',
        public string $modeloIa = 'gemini-2.5-flash',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'barreiras' => $this->barreiras,
            'adaptacoes_praticas' => $this->adaptacoesPraticas,
            'adaptacao_avaliacao' => $this->adaptacaoAvaliacao,
            'estrategias_professor' => $this->estrategiasProfessor,
            'indicadores_acompanhamento' => $this->indicadoresAcompanhamento,
            'prazo_revisao_dias' => $this->prazoRevisaoDias,
            'habilidade_bncc_alvo' => $this->habilidadeBnccAlvo,
            'profundidade_requer_validacao_aee' => $this->profundidadeRequerValidacaoAee,
            'aviso_legal' => $this->avisoLegal,
            'modelo_ia' => $this->modeloIa,
        ];
    }
}
