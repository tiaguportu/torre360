<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Concorrente;
use App\Models\Interessado;
use App\Models\Objecao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BattlecardService
{
    /**
     * Retorna a matriz de inteligência competitiva e ranking de perdas.
     *
     * @return array<string, mixed>
     */
    public function obterRadarConcorrencia(): array
    {
        $totalConcorrentes = Concorrente::ativos()->count();

        // Total de perdas registradas para concorrentes
        $totalPerdasConcorrencia = Interessado::whereNotNull('concorrente_id')->count();

        // Ranking dos concorrentes que mais ganharam alunos
        $rankingConcorrentes = Concorrente::has('interessadosPerdidos')
            ->withCount('interessadosPerdidos')
            ->orderByDesc('interessados_perdidos_count')
            ->limit(10)
            ->get()
            ->map(fn (Concorrente $c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'sigla' => $c->sigla,
                'perdas' => $c->interessados_perdidos_count,
                'percentual' => $totalPerdasConcorrencia > 0
                    ? round(($c->interessados_perdidos_count / $totalPerdasConcorrencia) * 100, 1)
                    : 0.0,
                'faixa_preco' => $c->rotuloFaixaPreco(),
                'proposta' => $c->proposta_pedagogica,
            ]);

        // Fatores decisivos mais frequentes alegados pelas famílias
        $fatoresFrequentes = Interessado::whereNotNull('concorrente_id')
            ->whereNotNull('fator_decisivo_concorrente')
            ->select('fator_decisivo_concorrente', DB::raw('count(*) as total'))
            ->groupBy('fator_decisivo_concorrente')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($item) => [
                'fator' => $item->fator_decisivo_concorrente,
                'total' => $item->total,
                'percentual' => $totalPerdasConcorrencia > 0
                    ? round(($item->total / $totalPerdasConcorrencia) * 100, 1)
                    : 0.0,
            ]);

        return [
            'total_concorrentes' => $totalConcorrentes,
            'total_perdas' => $totalPerdasConcorrencia,
            'ranking' => $rankingConcorrentes,
            'fatores_decisao' => $fatoresFrequentes,
        ];
    }

    /**
     * Retorna a matriz de objeções ativas agrupadas ou filtradas.
     *
     * @return Collection<int, Objecao>
     */
    public function obterMatrizObjecoes(?string $categoria = null): Collection
    {
        $query = Objecao::ativos();

        if (! blank($categoria) && $categoria !== 'todos') {
            $query->porCategoria($categoria);
        }

        return $query->get();
    }

    /**
     * Gera uma estratégia e script de contra-argumento usando a IA Gemini.
     */
    public function gerarContraArgumentoIa(string $cenarioOuConcorrente, string $objecaoFamilia): string
    {
        $prompt = <<<PROMPT
Você é o especialista líder em admissões e vendas consultivas da escola particular "Torre de Marfim" (sistema Torre360).
Sua missão é fornecer um roteiro persuasivo, ético e empático para o consultor escolar responder à família.

DIRETRIZES FUNDAMENTAIS:
1. NUNCA fale mal ou ataque diretamente a outra escola. Venda valor educacional, segurança, acolhimento e método próprio.
2. Reconheça e valide o sentimento dos pais (empatia) antes de apresentar a solução.
3. Foque no futuro e desenvolvimento da criança (a decisão não é sobre tijolos ou preço, é sobre a formação do filho).
4. Termine sempre com uma "Pergunta de Ouro" aberta que faça os pais refletirem sobre o que realmente importa.

DADOS DA SITUAÇÃO:
- Concorrente / Contexto: {$cenarioOuConcorrente}
- Objeção verbalizada pela família: {$objecaoFamilia}

ESTRUTURE SUA RESPOSTA EXATAMENTE ASSIM:
### 💡 Diagnóstico Rápido
(1 a 2 frases curtas sobre o medo ou dor real dos pais por trás dessa objeção)

### 🗣️ O que falar para os pais (Roteiro em primeira pessoa):
"(Texto pronto entre aspas que o consultor pode falar com tom afetuoso, seguro e profissional)"

### ❓ Pergunta de Ouro para virar a conversa:
"(Uma pergunta aberta e reflexiva para os pais responderem)"
PROMPT;

        /** @var GeminiAgentService $gemini */
        $gemini = app(GeminiAgentService::class);

        return $gemini->executar($prompt);
    }
}
