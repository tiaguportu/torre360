<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CategoriaItemMaterial;
use App\Enums\StatusListaMaterial;
use App\Exceptions\ListaMaterialException;
use App\Models\ItemListaMaterial;
use App\Models\ListaMaterialEscolar;
use App\Models\Matricula;
use App\Models\Serie;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lista de material escolar por série e período (Onda 59).
 *
 * A trava segue a Lei nº 9.870/1999, art. 1º, § 7º (Lei nº 12.886/2013): material de uso coletivo dos estudantes ou da
 * escola não pode ser exigido da família; o custo entra na anuidade. Cada item é avaliado pela descrição:
 *
 * - **bloqueado**: uso coletivo evidente (papel higiênico, material de limpeza, giz, pincel de quadro, toner...). Não
 *   entra na lista.
 * - **atenção**: pode ser individual ou coletivo (resma, fita adesiva, clips...) ou a quantidade é alta. Entra só com a
 *   confirmação de quem monta de que é de uso individual do aluno e em quantidade coerente.
 * - **ok**: o resto.
 *
 * Os termos ficam em `config/material_escolar.php`. É um apoio a quem monta a lista, não uma decisão jurídica. A
 * lista não tem campo de marca nem de loja: exigir uma ou outra é venda casada (CDC, art. 39, I).
 */
class ListaMaterialService
{
    public const OK = 'ok';

    public const ATENCAO = 'atencao';

    public const BLOQUEADO = 'bloqueado';

    /**
     * @return array{nivel: string, motivo: ?string}
     */
    public function avaliar(string $descricao, int $quantidade = 1): array
    {
        $texto = $this->normalizar($descricao);

        foreach ((array) config('material_escolar.termos_bloqueados', []) as $termo) {
            if ($this->contem($texto, (string) $termo) && ! $this->coberto($texto, (string) $termo)) {
                return [
                    'nivel' => self::BLOQUEADO,
                    'motivo' => "\"{$termo}\" é material de uso coletivo da escola: o custo deve estar na mensalidade (Lei nº 9.870/1999, art. 1º, § 7º). Não pode constar na lista.",
                ];
            }
        }

        foreach ((array) config('material_escolar.termos_atencao', []) as $termo) {
            if ($this->contem($texto, (string) $termo)) {
                return [
                    'nivel' => self::ATENCAO,
                    'motivo' => "\"{$termo}\" pode ser de uso coletivo. Só inclua se for de uso individual do aluno e em quantidade coerente com as atividades.",
                ];
            }
        }

        $limite = (int) config('material_escolar.quantidade_sem_confirmacao', 30);

        if ($quantidade >= $limite) {
            return [
                'nivel' => self::ATENCAO,
                'motivo' => "A quantidade ({$quantidade}) é alta para uso individual. Confirme que é coerente com as atividades do aluno.",
            ];
        }

        return ['nivel' => self::OK, 'motivo' => null];
    }

    /**
     * Cria ou atualiza um item, aplicando a trava.
     *
     * @param  array<string, mixed>  $dados  descricao, quantidade, unidade, categoria, observacao, uso_individual_confirmado
     *
     * @throws ListaMaterialException
     */
    public function salvarItem(ListaMaterialEscolar $lista, array $dados, ?ItemListaMaterial $item = null): ItemListaMaterial
    {
        $this->exigirRascunho($lista);

        if ($item && $item->lista_material_id !== $lista->id) {
            throw new ListaMaterialException('O item não pertence a esta lista.');
        }

        $descricao = trim((string) preg_replace('/\s+/u', ' ', (string) ($dados['descricao'] ?? '')));

        if (mb_strlen($descricao) < 3 || mb_strlen($descricao) > 160) {
            throw new ListaMaterialException('Descreva o item com 3 a 160 caracteres.');
        }

        $quantidade = (int) ($dados['quantidade'] ?? 1);

        if ($quantidade < 1 || $quantidade > 999) {
            throw new ListaMaterialException('A quantidade deve ficar entre 1 e 999.');
        }

        $avaliacao = $this->avaliar($descricao, $quantidade);

        if ($avaliacao['nivel'] === self::BLOQUEADO) {
            throw new ListaMaterialException((string) $avaliacao['motivo']);
        }

        $confirmado = (bool) ($dados['uso_individual_confirmado'] ?? false);

        if ($avaliacao['nivel'] === self::ATENCAO && ! $confirmado) {
            throw new ListaMaterialException($avaliacao['motivo'].' Marque a confirmação de uso individual para incluir.');
        }

        $categoria = $dados['categoria'] ?? CategoriaItemMaterial::Papelaria;
        $categoria = $categoria instanceof CategoriaItemMaterial ? $categoria : (CategoriaItemMaterial::tryFrom((string) ($categoria instanceof BackedEnum ? $categoria->value : $categoria)) ?? CategoriaItemMaterial::Outros);

        $atributos = [
            'descricao' => $descricao,
            'quantidade' => $quantidade,
            'unidade' => filled($dados['unidade'] ?? null) ? mb_substr(trim((string) $dados['unidade']), 0, 30) : null,
            'categoria' => $categoria,
            'observacao' => filled($dados['observacao'] ?? null) ? mb_substr(trim((string) $dados['observacao']), 0, 255) : null,
            'uso_individual_confirmado' => $avaliacao['nivel'] === self::ATENCAO && $confirmado,
        ];

        if ($item) {
            $item->update($atributos);

            return $item;
        }

        // reorder(): agregado com o ORDER BY padrão da relação é recusado pelo MySQL com ONLY_FULL_GROUP_BY.
        return $lista->itens()->create($atributos + ['ordem' => (int) $lista->itens()->reorder()->max('ordem') + 1]);
    }

    /**
     * @throws ListaMaterialException
     */
    public function removerItem(ItemListaMaterial $item): void
    {
        $this->exigirRascunho($item->lista()->firstOrFail());

        $item->delete();
    }

    /**
     * O que impede a publicação: lista vazia, item de uso coletivo (a configuração pode ter mudado depois de salvo) e
     * item de atenção sem confirmação.
     *
     * @return list<string>
     */
    public function pendencias(ListaMaterialEscolar $lista): array
    {
        $itens = $lista->itens()->get();

        if ($itens->isEmpty()) {
            return ['A lista não tem itens.'];
        }

        $pendencias = [];

        foreach ($itens as $item) {
            $avaliacao = $this->avaliar($item->descricao, $item->quantidade);

            if ($avaliacao['nivel'] === self::BLOQUEADO) {
                $pendencias[] = "\"{$item->descricao}\": ".$avaliacao['motivo'];
            } elseif ($avaliacao['nivel'] === self::ATENCAO && ! $item->uso_individual_confirmado) {
                $pendencias[] = "\"{$item->descricao}\" precisa da confirmação de uso individual.";
            }
        }

        return $pendencias;
    }

    /**
     * @throws ListaMaterialException
     */
    public function publicar(ListaMaterialEscolar $lista, ?User $por): ListaMaterialEscolar
    {
        $this->exigirRascunho($lista, 'A lista já está publicada.');

        if ($pendencias = $this->pendencias($lista)) {
            throw new ListaMaterialException(implode(' ', $pendencias));
        }

        $lista->forceFill([
            'status' => StatusListaMaterial::Publicada,
            'publicada_em' => now(),
            'publicada_por_user_id' => $por?->id,
        ])->saveQuietly();

        activity('lista_material')->causedBy($por)->performedOn($lista)->withProperties(['itens' => $lista->itens()->reorder()->count()])->log('Publicou a lista de material escolar');

        return $lista;
    }

    /**
     * Volta a lista para rascunho (para corrigir). As famílias deixam de vê-la até a nova publicação.
     *
     * @throws ListaMaterialException
     */
    public function despublicar(ListaMaterialEscolar $lista, ?User $por): ListaMaterialEscolar
    {
        if (! $lista->estaPublicada()) {
            throw new ListaMaterialException('A lista não está publicada.');
        }

        $lista->forceFill(['status' => StatusListaMaterial::Rascunho, 'publicada_em' => null, 'publicada_por_user_id' => null])->saveQuietly();

        activity('lista_material')->causedBy($por)->performedOn($lista)->log('Voltou a lista de material escolar para rascunho');

        return $lista;
    }

    /**
     * Copia os itens de outra lista (por exemplo, a do ano anterior). A lista de destino precisa estar em rascunho e vazia.
     * Itens que a trava atual não aceita ficam de fora, com o motivo.
     *
     * @return array{copiados: int, ignorados: list<string>}
     *
     * @throws ListaMaterialException
     */
    public function copiar(ListaMaterialEscolar $origem, ListaMaterialEscolar $destino): array
    {
        $this->exigirRascunho($destino);

        if ($origem->is($destino)) {
            throw new ListaMaterialException('Escolha outra lista para copiar.');
        }

        if ($destino->itens()->exists()) {
            throw new ListaMaterialException('A lista de destino já tem itens. Copie para uma lista vazia.');
        }

        $copiados = 0;
        $ignorados = [];

        DB::transaction(function () use ($origem, $destino, &$copiados, &$ignorados): void {
            foreach ($origem->itens()->get() as $item) {
                $avaliacao = $this->avaliar($item->descricao, $item->quantidade);

                if ($avaliacao['nivel'] === self::BLOQUEADO || ($avaliacao['nivel'] === self::ATENCAO && ! $item->uso_individual_confirmado)) {
                    $ignorados[] = "\"{$item->descricao}\": ".$avaliacao['motivo'];

                    continue;
                }

                $destino->itens()->create([
                    'descricao' => $item->descricao,
                    'quantidade' => $item->quantidade,
                    'unidade' => $item->unidade,
                    'categoria' => $item->categoria,
                    'observacao' => $item->observacao,
                    'uso_individual_confirmado' => $item->uso_individual_confirmado,
                    'ordem' => ++$copiados,
                ]);
            }
        });

        activity('lista_material')->causedBy(auth()->user())->performedOn($destino)->withProperties(['origem_id' => $origem->id, 'copiados' => $copiados, 'ignorados' => count($ignorados)])->log('Copiou os itens de outra lista de material escolar');

        return ['copiados' => $copiados, 'ignorados' => $ignorados];
    }

    /**
     * Lista publicada da série e do período da turma do aluno, com os itens.
     */
    public function listaPublicadaDoAluno(Matricula $matricula): ?ListaMaterialEscolar
    {
        $turma = $matricula->turma;

        if (! $turma || ! $turma->serie_id || ! $turma->periodo_letivo_id) {
            return null;
        }

        return ListaMaterialEscolar::query()
            ->where('serie_id', $turma->serie_id)
            ->where('periodo_letivo_id', $turma->periodo_letivo_id)
            ->where('status', StatusListaMaterial::Publicada->value)
            ->with(['itens', 'serie', 'periodoLetivo'])
            ->first();
    }

    /**
     * A família vê a lista publicada da série e do período de algum dos seus alunos.
     */
    public function familiaPodeVer(User $user, ListaMaterialEscolar $lista): bool
    {
        if (! $lista->estaPublicada()) {
            return false;
        }

        return Matricula::query()
            ->without('turma')
            ->whereIn('pessoa_id', $user->pessoasAcessiveis()->pluck('id'))
            ->whereHas('turma', fn ($q) => $q->where('serie_id', $lista->serie_id)->where('periodo_letivo_id', $lista->periodo_letivo_id))
            ->exists();
    }

    /**
     * Séries (entre as informadas) que ainda não têm lista publicada no período.
     *
     * @param  iterable<int>  $serieIds
     * @return Collection<int, Serie>
     */
    public function seriesSemListaPublicada(int $periodoLetivoId, iterable $serieIds): Collection
    {
        $ids = collect($serieIds)->unique()->values();

        $comLista = ListaMaterialEscolar::query()
            ->where('periodo_letivo_id', $periodoLetivoId)
            ->where('status', StatusListaMaterial::Publicada->value)
            ->whereIn('serie_id', $ids)
            ->pluck('serie_id');

        return Serie::query()->whereIn('id', $ids->diff($comLista))->orderBy('nome')->get();
    }

    /**
     * Itens agrupados por categoria, na ordem do enum, para a tela e o PDF.
     *
     * @return Collection<string, Collection<int, ItemListaMaterial>>
     */
    public function itensPorCategoria(ListaMaterialEscolar $lista): Collection
    {
        $itens = $lista->relationLoaded('itens') ? $lista->itens : $lista->itens()->get();

        return collect(CategoriaItemMaterial::cases())
            ->mapWithKeys(fn (CategoriaItemMaterial $categoria): array => [$categoria->getLabel() => $itens->filter(fn (ItemListaMaterial $i): bool => $i->categoria === $categoria)->values()])
            ->filter(fn (Collection $grupo): bool => $grupo->isNotEmpty());
    }

    private function exigirRascunho(ListaMaterialEscolar $lista, string $mensagem = 'A lista está publicada. Volte-a para rascunho para alterar os itens.'): void
    {
        if ($lista->estaPublicada()) {
            throw new ListaMaterialException($mensagem);
        }
    }

    private function normalizar(string $texto): string
    {
        return (string) Str::of($texto)->ascii()->lower()->squish();
    }

    /**
     * O termo aparece no começo de uma palavra do texto.
     */
    private function contem(string $texto, string $termo): bool
    {
        $termo = $this->normalizar($termo);

        return $termo !== '' && preg_match('/(?<![a-z0-9])'.preg_quote($termo, '/').'/', $texto) === 1;
    }

    /**
     * Uma expressão da lista de exceções (que contém o termo) também aparece no texto: "giz de cera" cobre "giz".
     */
    private function coberto(string $texto, string $termo): bool
    {
        $termo = $this->normalizar($termo);

        foreach ((array) config('material_escolar.excecoes', []) as $excecao) {
            $excecao = $this->normalizar((string) $excecao);

            if (str_contains($excecao, $termo) && $this->contem($texto, $excecao)) {
                return true;
            }
        }

        return false;
    }
}
