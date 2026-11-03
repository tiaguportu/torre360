<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AtendimentoChamado;
use App\Models\ListaEsperaMatricula;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Quem, da família, recebe um aviso. Resolve de "pessoa" (aluno, responsável) para "conta" (usuário do Portal) num lugar só.
 *
 * Antes havia cinco implementações com regras parecidas e diferentes: a da matrícula, a da lista de espera, a da preceptoria, a do
 * resumo diário de faltas e a do chamado. Cada regra continua a mesma, agora com nome e teste; quem precisa de uma regra nova
 * escolhe uma destas ou acrescenta a sua aqui, em vez de montar `responsaveis->users` de novo.
 *
 * As regras que existem:
 *  - {@see responsaveisDoAluno}: as contas dos responsáveis, sem a do próprio aluno (faltas, chamados).
 *  - {@see daMatricula}: o aluno, os responsáveis financeiros do contrato e os responsáveis do aluno, só contas com e-mail.
 *  - {@see daListaDeEspera}: o aluno, os responsáveis e quem registrou a entrada.
 *  - {@see notificaveisDasPessoas}: a pessoa com e-mail e as contas dela, uma vez por e-mail (preceptoria).
 *  - {@see doChamado}: a conta de quem abriu o chamado, ou as dos responsáveis do aluno; só contas ativas.
 *
 * Assinatura de contrato (Assinafy, `SignatariosSugeridosService`) é outro assunto: lá o que importa é quem assina, e não quem é avisado.
 */
class DestinatariosDaFamilia
{
    /**
     * As contas dos responsáveis do aluno. O próprio aluno não entra: é o caso de aviso que fala do aluno (falta, ocorrência).
     *
     * @return Collection<int, User>
     */
    public function responsaveisDoAluno(?Pessoa $aluno, bool $apenasAtivos = false): Collection
    {
        if (! $aluno) {
            return collect();
        }

        $aluno->loadMissing('responsaveis.users');

        return $this->contasAtivasSe($aluno->responsaveis->flatMap(fn (Pessoa $responsavel) => $responsavel->users), $apenasAtivos);
    }

    /**
     * O aluno, os responsáveis financeiros do contrato e os responsáveis do aluno: as contas deles que têm e-mail.
     *
     * @return Collection<int, User>
     */
    public function daMatricula(Matricula $matricula): Collection
    {
        $matricula->loadMissing(['pessoa.responsaveis', 'contrato.responsaveisFinanceiros.pessoa']);

        $pessoas = collect();

        if ($matricula->pessoa) {
            $pessoas->push($matricula->pessoa);
        }

        foreach ($matricula->contrato?->responsaveisFinanceiros ?? [] as $financeiro) {
            if ($financeiro->pessoa) {
                $pessoas->push($financeiro->pessoa);
            }
        }

        foreach ($matricula->pessoa?->responsaveis ?? [] as $responsavel) {
            $pessoas->push($responsavel);
        }

        if ($pessoas->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereHas('pessoas', fn ($consulta) => $consulta->whereIn('pessoa.id', $pessoas->pluck('id')->unique()))
            ->whereNotNull('email')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * O aluno que espera a vaga, os responsáveis dele e quem registrou a entrada na lista.
     *
     * @return Collection<int, User>
     */
    public function daListaDeEspera(ListaEsperaMatricula $entrada): Collection
    {
        $entrada->loadMissing(['pessoa.users', 'pessoa.responsaveis.users', 'criadoPor']);

        $destinatarios = collect([$entrada->pessoa])
            ->merge($entrada->pessoa?->responsaveis ?? [])
            ->filter()
            ->flatMap(fn (Pessoa $pessoa) => $pessoa->users);

        if ($entrada->criadoPor) {
            $destinatarios->push($entrada->criadoPor);
        }

        return $destinatarios->unique('id')->values();
    }

    /**
     * Das pessoas aos destinatários que de fato recebem: a própria pessoa (se tiver e-mail, e então ela é notificada direto) e as
     * contas vinculadas a ela que têm e-mail. Uma vez por e-mail: se a pessoa e a conta dela têm o mesmo, fica a conta, que também recebe
     * push e sino.
     *
     * @param  Collection<int, Pessoa>  $pessoas
     * @return Collection<int, Pessoa|User>
     */
    public function notificaveisDasPessoas(Collection $pessoas): Collection
    {
        $destinatarios = collect();

        foreach ($pessoas->unique('id') as $pessoa) {
            $pessoa->loadMissing('users');

            if ($pessoa->email) {
                $destinatarios->push($pessoa);
            }

            foreach ($pessoa->users as $usuario) {
                if ($usuario->email) {
                    $destinatarios->push($usuario);
                }
            }
        }

        return $destinatarios
            ->groupBy('email')
            ->map(fn (Collection $grupo) => $grupo->first(fn ($item) => $item instanceof User) ?? $grupo->first())
            ->values();
    }

    /**
     * Quem recebe o aviso de um chamado: a conta de quem abriu; sem conta, as dos responsáveis do aluno do chamado. Só contas ativas.
     *
     * @return Collection<int, User>
     */
    public function doChamado(AtendimentoChamado $chamado): Collection
    {
        $chamado->loadMissing(['solicitante.users', 'matricula.pessoa.responsaveis.users']);

        $contas = $chamado->solicitante?->users ?? collect();

        if ($contas->isEmpty()) {
            $contas = $this->responsaveisDoAluno($chamado->matricula?->pessoa);
        }

        return $this->contasAtivasSe($contas, true);
    }

    /**
     * @param  Collection<int, User>  $contas
     * @return Collection<int, User>
     */
    private function contasAtivasSe(Collection $contas, bool $apenasAtivos): Collection
    {
        if ($apenasAtivos) {
            $contas = $contas->filter(fn (User $conta): bool => $conta->is_active);
        }

        return $contas->unique('id')->values();
    }
}
