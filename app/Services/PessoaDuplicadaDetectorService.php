<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pessoa;
use App\Models\PessoaNaoDuplicada;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Detecção de cadastros de pessoa possivelmente duplicados (Onda 81). O equivalente do `LeadDuplicadoDetectorService` para `Pessoa`.
 *
 * O resultado é uma lista de PARES de cadastros com o motivo de cada um. Só sugere: nunca une, apaga ou altera nada. Os critérios
 * existem para derrubar o falso positivo, que é o que estraga uma fila de conferência:
 *  - CPF igual (só dígitos; CPF de marcador como 000.000.000-00 é ignorado);
 *  - mesmo nome (sem acento, caixa ou pontuação, com ao menos duas palavras) e mesma data de nascimento;
 *  - mesmo nome e mesmo e-mail ou telefone, quando as datas de nascimento não se contradizem e os CPFs não são diferentes.
 *
 * E-mail ou telefone iguais SEM o mesmo nome nunca formam par: pai, mãe e filhos compartilham contato. Gêmeos têm nomes diferentes.
 * Homônimos com nascimentos diferentes não casam. Quem já tem vínculo direto (responsável e aluno) não é par. E o par que a secretaria
 * marcou como "não é duplicata" não volta.
 */
class PessoaDuplicadaDetectorService
{
    public const MOTIVO_CPF = 'cpf';

    public const MOTIVO_NOME_NASCIMENTO = 'nome_nascimento';

    public const MOTIVO_NOME_CONTATO = 'nome_contato';

    /** Texto de cada motivo, na ordem da força da evidência. */
    public const ROTULOS = [
        self::MOTIVO_CPF => 'CPF igual',
        self::MOTIVO_NOME_NASCIMENTO => 'Mesmo nome e nascimento',
        self::MOTIVO_NOME_CONTATO => 'Mesmo nome e contato',
    ];

    private const PESO = [
        self::MOTIVO_CPF => 3,
        self::MOTIVO_NOME_NASCIMENTO => 2,
        self::MOTIVO_NOME_CONTATO => 1,
    ];

    /** Valor repartido por mais cadastros que isso é marcador de lugar (CPF de teste, telefone da escola): não forma par. */
    private const MAX_POR_CHAVE = 6;

    private const CACHE_CHAVE = 'pessoa_duplicada.pares';

    private const CACHE_SEGUNDOS = 120;

    /**
     * Pares possíveis, do mais provável ao menos. Guardado por poucos minutos: varre a tabela inteira de pessoas.
     *
     * @return Collection<int, array{chave: string, pessoa_menor_id: int, pessoa_maior_id: int, motivos: list<string>}>
     */
    public function pares(): Collection
    {
        return collect(Cache::remember(self::CACHE_CHAVE, self::CACHE_SEGUNDOS, fn (): array => $this->calcular()));
    }

    public function esquecerCache(): void
    {
        Cache::forget(self::CACHE_CHAVE);
    }

    /**
     * A secretaria conferiu os dois cadastros e são pessoas diferentes (gêmeos, homônimos): o par não é sugerido de novo.
     * A ordem dos argumentos não importa.
     */
    public function marcarComoDiferentes(Pessoa|int $a, Pessoa|int $b, ?User $usuario = null): PessoaNaoDuplicada
    {
        $idA = $a instanceof Pessoa ? (int) $a->getKey() : $a;
        $idB = $b instanceof Pessoa ? (int) $b->getKey() : $b;

        if ($idA === $idB) {
            throw new InvalidArgumentException('Um cadastro não pode ser marcado como diferente dele mesmo.');
        }

        $usuario ??= auth()->user();

        $marcacao = PessoaNaoDuplicada::query()->firstOrCreate([
            'pessoa_menor_id' => min($idA, $idB),
            'pessoa_maior_id' => max($idA, $idB),
        ], [
            'user_id' => $usuario?->getKey(),
        ]);

        $this->esquecerCache();

        return $marcacao;
    }

    /**
     * @return list<array{chave: string, pessoa_menor_id: int, pessoa_maior_id: int, motivos: list<string>}>
     */
    private function calcular(): array
    {
        $pessoas = $this->carregar();
        $arestas = [];

        // 1. CPF igual
        foreach ($this->agrupar($pessoas, 'cpf') as $ids) {
            foreach ($this->combinacoes($ids) as [$a, $b]) {
                $this->registrar($arestas, $a, $b, self::MOTIVO_CPF);
            }
        }

        // 2. Mesmo nome (+ nascimento, ou + contato)
        foreach ($this->agrupar($pessoas, 'nome', apenasNomeComposto: true) as $ids) {
            foreach ($this->combinacoes($ids) as [$a, $b]) {
                $this->compararMesmoNome($arestas, $pessoas[$a], $pessoas[$b]);
            }
        }

        if ($arestas === []) {
            return [];
        }

        $arestas = $this->semVinculoDireto($arestas);
        $arestas = $this->semMarcadosComoDiferentes($arestas);

        $pares = [];

        foreach ($arestas as $chave => $motivos) {
            [$menor, $maior] = array_map('intval', explode('-', $chave));

            // Ordem fixa dos motivos: do mais forte ao mais fraco.
            $motivos = array_values(array_intersect(array_keys(self::ROTULOS), array_keys($motivos)));

            $pares[] = [
                'chave' => $chave,
                'pessoa_menor_id' => $menor,
                'pessoa_maior_id' => $maior,
                'motivos' => $motivos,
            ];
        }

        usort($pares, function (array $x, array $y): int {
            return [$this->pesoDe($y['motivos']), count($y['motivos']), $x['pessoa_menor_id'], $x['pessoa_maior_id']]
                <=> [$this->pesoDe($x['motivos']), count($x['motivos']), $y['pessoa_menor_id'], $y['pessoa_maior_id']];
        });

        return $pares;
    }

    /**
     * @param  array<string, array<string, true>>  $arestas
     * @param  array{id: int, nome: string, cpf: string, email: string, telefone: string, nascimento: string}  $a
     * @param  array{id: int, nome: string, cpf: string, email: string, telefone: string, nascimento: string}  $b
     */
    private function compararMesmoNome(array &$arestas, array $a, array $b): void
    {
        $mesmoNascimento = $a['nascimento'] !== '' && $a['nascimento'] === $b['nascimento'];
        $nascimentosContradizem = $a['nascimento'] !== '' && $b['nascimento'] !== '' && ! $mesmoNascimento;
        $cpfsDiferentes = $a['cpf'] !== '' && $b['cpf'] !== '' && $a['cpf'] !== $b['cpf'];

        // Mesmo nome e mesmo dia de nascimento: duas pessoas assim são raras; o CPF diferente pode ser só um erro de digitação.
        if ($mesmoNascimento) {
            $this->registrar($arestas, $a['id'], $b['id'], self::MOTIVO_NOME_NASCIMENTO);
        }

        // Mesmo nome e mesmo contato só vale quando nada contradiz: nascimentos diferentes (pai e filho com o mesmo nome) ou CPFs diferentes.
        if ($nascimentosContradizem || $cpfsDiferentes) {
            return;
        }

        $mesmoEmail = $a['email'] !== '' && $a['email'] === $b['email'];
        $mesmoTelefone = $a['telefone'] !== '' && $a['telefone'] === $b['telefone'];

        if ($mesmoEmail || $mesmoTelefone) {
            $this->registrar($arestas, $a['id'], $b['id'], self::MOTIVO_NOME_CONTATO);
        }
    }

    /**
     * Só as colunas de que a comparação precisa, em lotes, já normalizadas.
     *
     * @return array<int, array{id: int, nome: string, cpf: string, email: string, telefone: string, nascimento: string}>
     */
    private function carregar(): array
    {
        $pessoas = [];

        Pessoa::query()
            ->select(['id', 'nome', 'cpf', 'email', 'telefone', 'data_nascimento'])
            ->lazyById(2000)
            ->each(function (Pessoa $pessoa) use (&$pessoas): void {
                $id = (int) $pessoa->getKey();

                $pessoas[$id] = [
                    'id' => $id,
                    'nome' => $this->normalizarNome($pessoa->nome),
                    'cpf' => $this->normalizarCpf($pessoa->cpf),
                    'email' => $this->normalizarEmail($pessoa->email),
                    'telefone' => $this->normalizarTelefone($pessoa->telefone),
                    'nascimento' => $this->normalizarNascimento($pessoa->data_nascimento),
                ];
            });

        return $pessoas;
    }

    /**
     * Ids que dividem o mesmo valor (de 2 até MAX_POR_CHAVE cadastros), em ordem crescente de id.
     *
     * @param  array<int, array<string, mixed>>  $pessoas
     * @return list<list<int>>
     */
    private function agrupar(array $pessoas, string $campo, bool $apenasNomeComposto = false): array
    {
        $grupos = [];

        foreach ($pessoas as $id => $pessoa) {
            $valor = (string) $pessoa[$campo];

            if ($valor === '' || ($apenasNomeComposto && ! str_contains($valor, ' '))) {
                continue;
            }

            $grupos[$valor][] = $id;
        }

        return array_values(array_filter(
            $grupos,
            fn (array $ids): bool => count($ids) >= 2 && count($ids) <= self::MAX_POR_CHAVE,
        ));
    }

    /**
     * @param  list<int>  $ids  em ordem crescente
     * @return list<array{0: int, 1: int}>
     */
    private function combinacoes(array $ids): array
    {
        $pares = [];
        $total = count($ids);

        for ($i = 0; $i < $total - 1; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                $pares[] = [$ids[$i], $ids[$j]];
            }
        }

        return $pares;
    }

    /**
     * @param  array<string, array<string, true>>  $arestas
     */
    private function registrar(array &$arestas, int $a, int $b, string $motivo): void
    {
        $chave = min($a, $b).'-'.max($a, $b);
        $arestas[$chave][$motivo] = true;
    }

    /**
     * Responsável e aluno ligados diretamente (`aluno_responsavel`) são pessoas diferentes por definição.
     *
     * @param  array<string, array<string, true>>  $arestas
     * @return array<string, array<string, true>>
     */
    private function semVinculoDireto(array $arestas): array
    {
        $candidatos = [];

        foreach (array_keys($arestas) as $chave) {
            foreach (explode('-', $chave) as $id) {
                $candidatos[(int) $id] = true;
            }
        }

        foreach (array_chunk(array_keys($candidatos), 500) as $lote) {
            DB::table('aluno_responsavel')
                ->whereIn('aluno_id', $lote)
                ->get(['aluno_id', 'responsavel_id'])
                ->each(function (object $vinculo) use (&$arestas): void {
                    $chave = min((int) $vinculo->aluno_id, (int) $vinculo->responsavel_id).'-'.max((int) $vinculo->aluno_id, (int) $vinculo->responsavel_id);
                    unset($arestas[$chave]);
                });
        }

        return $arestas;
    }

    /**
     * @param  array<string, array<string, true>>  $arestas
     * @return array<string, array<string, true>>
     */
    private function semMarcadosComoDiferentes(array $arestas): array
    {
        PessoaNaoDuplicada::query()
            ->get(['pessoa_menor_id', 'pessoa_maior_id'])
            ->each(function (PessoaNaoDuplicada $marcacao) use (&$arestas): void {
                unset($arestas[$marcacao->pessoa_menor_id.'-'.$marcacao->pessoa_maior_id]);
            });

        return $arestas;
    }

    /**
     * @param  list<string>  $motivos
     */
    private function pesoDe(array $motivos): int
    {
        return $motivos === [] ? 0 : max(array_map(fn (string $motivo): int => self::PESO[$motivo], $motivos));
    }

    /** Sem acento, caixa nem pontuação, com os espaços reduzidos: "  JOSÉ  da Silva." e "jose da silva" são o mesmo nome. */
    private function normalizarNome(?string $nome): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $nome))) ?? '');
    }

    /** Onze dígitos, e nem todos iguais (000.000.000-00 e semelhantes são marcador de lugar, não identificam ninguém). */
    private function normalizarCpf(?string $cpf): string
    {
        $digitos = preg_replace('/\D/', '', (string) $cpf) ?? '';

        if (strlen($digitos) !== 11 || count(array_unique(str_split($digitos))) === 1) {
            return '';
        }

        return $digitos;
    }

    private function normalizarEmail(?string $email): string
    {
        $email = mb_strtolower(trim((string) $email));

        return str_contains($email, '@') ? $email : '';
    }

    /** Dez ou onze dígitos (DDD + número), sem o 55 do país. Menos que isso não identifica ninguém. */
    private function normalizarTelefone(?string $telefone): string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone) ?? '';

        if (strlen($digitos) >= 12 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        return in_array(strlen($digitos), [10, 11], true) ? $digitos : '';
    }

    /** `Y-m-d`, ou vazio quando a data é ausente, zerada ou malformada (a coluna não tem cast: o SQLite grava com hora). */
    private function normalizarNascimento(mixed $valor): string
    {
        $data = substr((string) $valor, 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) === 1 && $data !== '0000-00-00' ? $data : '';
    }
}
