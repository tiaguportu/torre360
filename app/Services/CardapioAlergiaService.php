<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Alergeno;
use App\Models\Cardapio;
use App\Models\FichaMedica;
use App\Models\Matricula;
use Illuminate\Support\Str;

/**
 * Cruza os alérgenos declarados numa refeição com as restrições alimentares da ficha médica dos alunos.
 *
 * Três níveis, do mais ao menos certo, sempre apresentados para conferência humana (nunca como garantia):
 * - **confirmado**: o aluno tem o campo estruturado da ficha (lactose, glúten, amendoim);
 * - **possível**: o texto livre das restrições cita o alérgeno (ex.: "não pode ovo", "intolerância a leite").
 *   O texto não é interpretado: "não tem alergia a leite" também casa, e por isso é só um alerta;
 * - **conferir**: o aluno tem texto livre de restrição, mas nada casou com os alérgenos declarados. O texto pode
 *   falar de um ingrediente que a descrição da refeição cita sem declarar como alérgeno.
 */
class CardapioAlergiaService
{
    /**
     * @return array{confirmados: list<Alergeno>, textuais: list<Alergeno>, tem_texto: bool, texto: string}
     */
    public function analisarFicha(?FichaMedica $ficha): array
    {
        if (! $ficha) {
            return ['confirmados' => [], 'textuais' => [], 'tem_texto' => false, 'texto' => ''];
        }

        $confirmados = [];
        $textuais = [];

        $textoBruto = trim(implode(' | ', array_filter([
            trim((string) $ficha->outras_alergias_alimentares),
            trim((string) $ficha->observacoes_alimentares),
        ])));
        $textoNormalizado = $this->normalizar($textoBruto);

        foreach (Alergeno::cases() as $alergeno) {
            $campo = $alergeno->campoFichaMedica();

            if ($campo !== null && (bool) $ficha->{$campo}) {
                $confirmados[] = $alergeno;
            }

            if ($textoNormalizado !== '' && $this->citaAlergeno($textoNormalizado, $alergeno)) {
                $textuais[] = $alergeno;
            }
        }

        return [
            'confirmados' => $confirmados,
            'textuais' => $textuais,
            'tem_texto' => $textoBruto !== '',
            'texto' => $textoBruto,
        ];
    }

    /**
     * O que há de conflito entre uma ficha e os alérgenos de uma refeição.
     *
     * @param  list<Alergeno>  $alergenosDaRefeicao
     * @return array{confirmados: list<Alergeno>, possiveis: list<Alergeno>}
     */
    public function conflitosDaFicha(?FichaMedica $ficha, array $alergenosDaRefeicao): array
    {
        $analise = $this->analisarFicha($ficha);
        $confirmados = $this->interseccao($alergenosDaRefeicao, $analise['confirmados']);
        $possiveis = array_values(array_filter(
            $this->interseccao($alergenosDaRefeicao, $analise['textuais']),
            fn (Alergeno $a): bool => ! in_array($a, $confirmados, true),
        ));

        return ['confirmados' => $confirmados, 'possiveis' => $possiveis];
    }

    /**
     * Alunos ativos com restrição que conflita com a refeição (ou com texto livre a conferir).
     *
     * @return array{confirmados: list<array<string, mixed>>, possiveis: list<array<string, mixed>>, conferir: list<array<string, mixed>>}
     */
    public function alunosAfetados(Cardapio $cardapio): array
    {
        $alergenos = $cardapio->alergenosEnum();
        $resultado = ['confirmados' => [], 'possiveis' => [], 'conferir' => []];

        $matriculas = Matricula::query()
            ->where('situacao', 'ativa')
            ->when($cardapio->unidade_id, fn ($q) => $q->whereHas('turma.serie.curso', fn ($c) => $c->where('unidade_id', $cardapio->unidade_id)))
            ->whereHas('pessoa.fichaMedica', fn ($ficha) => $ficha->where(fn ($w) => $w
                ->where('has_alergia_lactose', true)
                ->orWhere('has_alergia_gluten', true)
                ->orWhere('has_alergia_amendoim', true)
                ->orWhereRaw("TRIM(COALESCE(outras_alergias_alimentares, '')) <> ''")
                ->orWhereRaw("TRIM(COALESCE(observacoes_alimentares, '')) <> ''")))
            ->with(['pessoa.fichaMedica', 'turma'])
            ->get();

        foreach ($matriculas as $matricula) {
            $ficha = $matricula->pessoa?->fichaMedica;
            $analise = $this->analisarFicha($ficha);
            $conflitos = $this->conflitosDaFicha($ficha, $alergenos);

            $linha = [
                'matricula_id' => $matricula->id,
                'aluno' => (string) ($matricula->pessoa?->nome ?? 'Aluno'),
                'turma' => $matricula->turma?->nome,
                'alergenos' => [],
                'observacao' => $analise['tem_texto'] ? $analise['texto'] : null,
            ];

            if ($conflitos['confirmados'] !== []) {
                $resultado['confirmados'][] = array_merge($linha, ['alergenos' => $this->rotulos($conflitos['confirmados'])]);
            } elseif ($conflitos['possiveis'] !== []) {
                $resultado['possiveis'][] = array_merge($linha, ['alergenos' => $this->rotulos($conflitos['possiveis'])]);
            } elseif ($analise['tem_texto']) {
                $resultado['conferir'][] = $linha;
            }
        }

        foreach ($resultado as $chave => $linhas) {
            usort($linhas, fn (array $a, array $b): int => strcmp($a['aluno'], $b['aluno']));
            $resultado[$chave] = $linhas;
        }

        return $resultado;
    }

    private function citaAlergeno(string $textoNormalizado, Alergeno $alergeno): bool
    {
        foreach ($alergeno->palavrasChave() as $termo) {
            if (preg_match('/\b'.preg_quote($termo, '/').'\b/', $textoNormalizado) === 1) {
                return true;
            }
        }

        return false;
    }

    private function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();
    }

    /**
     * @param  list<Alergeno>  $a
     * @param  list<Alergeno>  $b
     * @return list<Alergeno>
     */
    private function interseccao(array $a, array $b): array
    {
        return array_values(array_filter($a, fn (Alergeno $alergeno): bool => in_array($alergeno, $b, true)));
    }

    /**
     * @param  list<Alergeno>  $alergenos
     * @return list<string>
     */
    private function rotulos(array $alergenos): array
    {
        return array_map(fn (Alergeno $a): string => $a->getLabel(), $alergenos);
    }
}
