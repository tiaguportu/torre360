<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusAdaptacaoPei;
use App\Models\Matricula;
use App\Models\PlanoAula;
use App\Models\PlanoAulaAdaptacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanoAulaAdaptacao>
 */
class PlanoAulaAdaptacaoFactory extends Factory
{
    protected $model = PlanoAulaAdaptacao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plano_aula_id' => PlanoAula::factory(),
            'matricula_id' => Matricula::factory(),
            'conteudo' => [
                'barreiras' => 'Dificuldade de concentração e processamento de textos longos.',
                'adaptacoes_praticas' => [
                    'instrucoes_passo_a_passo' => 'Fragmentar em cartões de etapas numeradas.',
                    'apoio_visual' => 'Uso de infográfico colorido e diagramas.',
                    'tempo_estendido' => 'Acréscimo de 15 minutos para leitura e síntese.',
                    'recursos_acessibilidade' => 'Leitor de tela e suporte com prancha de comunicação.',
                ],
                'adaptacao_avaliacao' => 'Avaliação oral complementar com apoio visual.',
                'estrategias_professor' => 'Checagem prévia de entendimento a cada 15 minutos.',
                'indicadores_acompanhamento' => 'Execução autônoma de pelo menos 2 etapas propostas.',
                'prazo_revisao_dias' => 15,
                'habilidade_bncc_alvo' => 'Mantida a complexidade original da habilidade.',
            ],
            'status' => StatusAdaptacaoPei::Rascunho,
            'gerado_por_user_id' => User::factory(),
            'revisado_por_user_id' => null,
            'revisado_em' => null,
            'aprovado_por_user_id' => null,
            'aprovado_em' => null,
            'modelo_ia' => 'gemini-2.5-flash',
        ];
    }
}
