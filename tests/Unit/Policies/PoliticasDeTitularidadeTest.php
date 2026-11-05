<?php

namespace Tests\Unit\Policies;

use App\Enums\StatusAutoavaliacaoDocente;
use App\Enums\StatusEncaminhamento;
use App\Enums\StatusPlanoDesenvolvimento;
use App\Enums\TipoReuniao;
use App\Models\AutoavaliacaoDocente;
use App\Models\Funcionario;
use App\Models\Interessado;
use App\Models\PlanoAula;
use App\Models\PlanoAulaAdaptacao;
use App\Models\PlanoDesenvolvimentoDocente;
use App\Models\PontoTratamento;
use App\Models\RelatorioSalvo;
use App\Models\Reuniao;
use App\Models\ReuniaoEncaminhamento;
use App\Policies\AutoavaliacaoDocentePolicy;
use App\Policies\InteressadoPolicy;
use App\Policies\PlanoAulaAdaptacaoPolicy;
use App\Policies\PlanoDesenvolvimentoDocentePolicy;
use App\Policies\PontoBancoHorasLancamentoPolicy;
use App\Policies\PontoRegistroPolicy;
use App\Policies\PontoTratamentoPolicy;
use App\Policies\RelatorioSalvoPolicy;
use App\Policies\ReuniaoEncaminhamentoPolicy;
use App\Policies\ReuniaoPolicy;
use App\Policies\UserPolicy;
use Tests\Support\UsuarioComPermissoes;
use Tests\TestCase;

/**
 * Policies em que a decisão depende de o registro ser "do próprio usuário" (autoavaliação, plano de desenvolvimento,
 * ponto, relatório, lead, reunião) ou de quem é o alvo (UserPolicy). Usuário e modelos em memória, sem banco.
 */
class PoliticasDeTitularidadeTest extends TestCase
{
    private const EU = 7;

    private const MINHA_PESSOA = 100;

    private const OUTRA_PESSOA = 200;

    /**
     * @param  list<string>  $permissoes
     */
    private function eu(array $permissoes = [], bool $professor = false, bool $veTodosLeads = false): UsuarioComPermissoes
    {
        return (new UsuarioComPermissoes($permissoes, self::EU, $professor, [], $veTodosLeads))->comPessoas([self::MINHA_PESSOA]);
    }

    private function funcionarioDe(int $pessoaId): Funcionario
    {
        return (new Funcionario)->forceFill(['pessoa_id' => $pessoaId]);
    }

    // ---------- Autoavaliação docente ----------

    private function autoavaliacao(StatusAutoavaliacaoDocente $status, int $pessoaId): AutoavaliacaoDocente
    {
        return (new AutoavaliacaoDocente)->forceFill(['status' => $status])->setRelation('funcionario', $this->funcionarioDe($pessoaId));
    }

    public function test_autoavaliacao_o_professor_ve_e_edita_so_a_propria_enquanto_rascunho(): void
    {
        $policy = new AutoavaliacaoDocentePolicy;
        $minha = $this->autoavaliacao(StatusAutoavaliacaoDocente::Rascunho, self::MINHA_PESSOA);
        $dela = $this->autoavaliacao(StatusAutoavaliacaoDocente::Rascunho, self::OUTRA_PESSOA);
        $professor = $this->eu(['View:AutoavaliacaoDocente', 'Update:AutoavaliacaoDocente', 'Enviar:AutoavaliacaoDocente'], professor: true);

        $this->assertTrue($policy->view($professor, $minha));
        $this->assertTrue($policy->update($professor, $minha));
        $this->assertTrue($policy->enviar($professor, $minha));

        $this->assertFalse($policy->view($professor, $dela));
        $this->assertFalse($policy->update($professor, $dela));
        $this->assertFalse($policy->enviar($professor, $dela));
    }

    public function test_autoavaliacao_enviada_nao_se_edita_nem_se_envia_de_novo(): void
    {
        $policy = new AutoavaliacaoDocentePolicy;
        $enviada = $this->autoavaliacao(StatusAutoavaliacaoDocente::Enviada, self::MINHA_PESSOA);
        $professor = $this->eu(['View:AutoavaliacaoDocente', 'Update:AutoavaliacaoDocente', 'Enviar:AutoavaliacaoDocente'], professor: true);

        $this->assertTrue($policy->view($professor, $enviada));
        $this->assertFalse($policy->update($professor, $enviada));
        $this->assertFalse($policy->enviar($professor, $enviada));
    }

    public function test_autoavaliacao_a_coordenacao_ve_so_as_enviadas_de_outros(): void
    {
        $policy = new AutoavaliacaoDocentePolicy;
        $coordenacao = $this->eu(['View:AutoavaliacaoDocente']);

        $this->assertTrue($policy->view($coordenacao, $this->autoavaliacao(StatusAutoavaliacaoDocente::Enviada, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->view($coordenacao, $this->autoavaliacao(StatusAutoavaliacaoDocente::Rascunho, self::OUTRA_PESSOA)), 'o rascunho é privado de quem escreve');
    }

    public function test_autoavaliacao_professor_nao_ve_a_enviada_de_colega(): void
    {
        $professor = $this->eu(['View:AutoavaliacaoDocente'], professor: true);

        $this->assertFalse((new AutoavaliacaoDocentePolicy)->view($professor, $this->autoavaliacao(StatusAutoavaliacaoDocente::Enviada, self::OUTRA_PESSOA)));
    }

    public function test_autoavaliacao_so_se_reabre_a_enviada(): void
    {
        $policy = new AutoavaliacaoDocentePolicy;
        $gestor = $this->eu(['Reabrir:AutoavaliacaoDocente']);

        $this->assertTrue($policy->reabrir($gestor, $this->autoavaliacao(StatusAutoavaliacaoDocente::Enviada, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->reabrir($gestor, $this->autoavaliacao(StatusAutoavaliacaoDocente::Rascunho, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->reabrir($this->eu(), $this->autoavaliacao(StatusAutoavaliacaoDocente::Enviada, self::OUTRA_PESSOA)));
    }

    // ---------- Plano de desenvolvimento docente ----------

    private function plano(StatusPlanoDesenvolvimento $status, int $pessoaId, array $mais = []): PlanoDesenvolvimentoDocente
    {
        return (new PlanoDesenvolvimentoDocente)->forceFill(['status' => $status] + $mais)->setRelation('funcionario', $this->funcionarioDe($pessoaId));
    }

    public function test_plano_professor_ve_so_o_proprio_e_so_depois_de_ativado(): void
    {
        $policy = new PlanoDesenvolvimentoDocentePolicy;
        $professor = $this->eu(['View:PlanoDesenvolvimentoDocente'], professor: true);

        $this->assertTrue($policy->view($professor, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::MINHA_PESSOA)));
        $this->assertFalse($policy->view($professor, $this->plano(StatusPlanoDesenvolvimento::Rascunho, self::MINHA_PESSOA)), 'rascunho ainda não é do professor');
        $this->assertFalse($policy->view($professor, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->view($this->eu([], professor: true), $this->plano(StatusPlanoDesenvolvimento::Ativo, self::MINHA_PESSOA)), 'sem a permissão View não vê nem o próprio');
    }

    public function test_plano_coordenacao_ve_todos(): void
    {
        $coordenacao = $this->eu(['View:PlanoDesenvolvimentoDocente']);

        $this->assertTrue((new PlanoDesenvolvimentoDocentePolicy)->view($coordenacao, $this->plano(StatusPlanoDesenvolvimento::Rascunho, self::OUTRA_PESSOA)));
    }

    public function test_plano_ninguem_edita_o_proprio_plano_nem_o_concluido(): void
    {
        $policy = new PlanoDesenvolvimentoDocentePolicy;
        $coordenacao = $this->eu(['Update:PlanoDesenvolvimentoDocente']);

        $this->assertTrue($policy->update($coordenacao, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->update($coordenacao, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::MINHA_PESSOA)), 'quem é o avaliado não edita o próprio plano');
        $this->assertFalse($policy->update($coordenacao, $this->plano(StatusPlanoDesenvolvimento::Concluido, self::OUTRA_PESSOA)));
    }

    public function test_plano_ativar_e_concluir_seguem_o_ciclo_rascunho_ativo_concluido(): void
    {
        $policy = new PlanoDesenvolvimentoDocentePolicy;
        $gestor = $this->eu(['Update:PlanoDesenvolvimentoDocente', 'Ativar:PlanoDesenvolvimentoDocente', 'Concluir:PlanoDesenvolvimentoDocente']);

        $this->assertTrue($policy->ativar($gestor, $this->plano(StatusPlanoDesenvolvimento::Rascunho, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->ativar($gestor, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::OUTRA_PESSOA)));
        $this->assertTrue($policy->concluir($gestor, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->concluir($gestor, $this->plano(StatusPlanoDesenvolvimento::Rascunho, self::OUTRA_PESSOA)));
    }

    public function test_plano_so_o_proprio_professor_da_ciencia_uma_vez(): void
    {
        $policy = new PlanoDesenvolvimentoDocentePolicy;
        $professor = $this->eu(['DarCiencia:PlanoDesenvolvimentoDocente'], professor: true);

        $this->assertTrue($policy->darCiencia($professor, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::MINHA_PESSOA)));
        $this->assertFalse($policy->darCiencia($professor, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::MINHA_PESSOA, ['ciencia_em' => '2026-10-01 09:00:00'])), 'ciência já dada');
        $this->assertFalse($policy->darCiencia($professor, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->darCiencia($professor, $this->plano(StatusPlanoDesenvolvimento::Rascunho, self::MINHA_PESSOA)));
    }

    public function test_plano_atualizar_acao_vale_ao_dono_ou_a_quem_edita_planos(): void
    {
        $policy = new PlanoDesenvolvimentoDocentePolicy;
        $dono = $this->eu(['AtualizarAcao:PlanoDesenvolvimentoDocente'], professor: true);
        $gestor = $this->eu(['AtualizarAcao:PlanoDesenvolvimentoDocente', 'Update:PlanoDesenvolvimentoDocente']);

        $this->assertTrue($policy->atualizarAcao($dono, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::MINHA_PESSOA)));
        $this->assertFalse($policy->atualizarAcao($dono, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::OUTRA_PESSOA)));
        $this->assertTrue($policy->atualizarAcao($gestor, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->atualizarAcao($gestor, $this->plano(StatusPlanoDesenvolvimento::Rascunho, self::OUTRA_PESSOA)));
    }

    public function test_plano_so_se_exclui_rascunho(): void
    {
        $policy = new PlanoDesenvolvimentoDocentePolicy;
        $gestor = $this->eu(['Delete:PlanoDesenvolvimentoDocente']);

        $this->assertTrue($policy->delete($gestor, $this->plano(StatusPlanoDesenvolvimento::Rascunho, self::OUTRA_PESSOA)));
        $this->assertFalse($policy->delete($gestor, $this->plano(StatusPlanoDesenvolvimento::Ativo, self::OUTRA_PESSOA)));
    }

    // ---------- Ponto: o ponto é dado pessoal do trabalhador ----------

    public function test_ponto_espelho_e_banco_de_horas_so_do_proprio_ou_de_quem_ve_todos(): void
    {
        $registro = new PontoRegistroPolicy;
        $banco = new PontoBancoHorasLancamentoPolicy;
        $meu = $this->funcionarioDe(self::MINHA_PESSOA);
        $dela = $this->funcionarioDe(self::OUTRA_PESSOA);

        $this->assertTrue($registro->verEspelho($this->eu(), $meu));
        $this->assertFalse($registro->verEspelho($this->eu(), $dela));
        $this->assertTrue($registro->verEspelho($this->eu(['VerEspelhoDeTodos:PontoRegistro']), $dela));

        $this->assertTrue($banco->ver($this->eu(), $meu));
        $this->assertFalse($banco->ver($this->eu(), $dela));
        $this->assertTrue($banco->ver($this->eu(['VerTodos:PontoBancoHorasLancamento']), $dela));
    }

    public function test_ponto_tratamento_so_o_proprio_ou_o_aprovador_ve(): void
    {
        $policy = new PontoTratamentoPolicy;
        $deles = fn (int $pessoaId) => (new PontoTratamento)->setRelation('funcionario', $this->funcionarioDe($pessoaId));

        $this->assertTrue($policy->view($this->eu(['View:PontoTratamento']), $deles(self::MINHA_PESSOA)));
        $this->assertFalse($policy->view($this->eu(['View:PontoTratamento']), $deles(self::OUTRA_PESSOA)));
        $this->assertTrue($policy->view($this->eu(['View:PontoTratamento', 'Aprovar:PontoTratamento']), $deles(self::OUTRA_PESSOA)));
        $this->assertFalse($policy->view($this->eu([]), $deles(self::MINHA_PESSOA)), 'sem View nem o próprio vê');
    }

    // ---------- Plano de aula: adaptação (PEI) com escopo de professor ----------

    private function adaptacaoDe(?PlanoAula $plano): PlanoAulaAdaptacao
    {
        return (new PlanoAulaAdaptacao)->setRelation('planoAula', $plano);
    }

    private function planoDe(?int $professorId): PlanoAula
    {
        return (new PlanoAula)->forceFill(['professor_id' => $professorId])->setRelation('turma', null);
    }

    public function test_pei_quem_nao_e_professor_nao_e_recortado(): void
    {
        $policy = new PlanoAulaAdaptacaoPolicy;
        $coordenacao = $this->eu(['GerarPei:PlanoAula', 'ExportarPei:PlanoAula']);
        $adaptacao = $this->adaptacaoDe($this->planoDe(self::OUTRA_PESSOA));

        $this->assertTrue($policy->viewAny($coordenacao));
        $this->assertTrue($policy->view($coordenacao, $adaptacao));
        $this->assertTrue($policy->update($coordenacao, $adaptacao));
        $this->assertTrue($policy->exportarPei($coordenacao, $adaptacao));
    }

    public function test_pei_professor_so_mexe_nos_planos_dele(): void
    {
        $policy = new PlanoAulaAdaptacaoPolicy;
        $professor = $this->eu(['GerarPei:PlanoAula', 'ExportarPei:PlanoAula'], professor: true);

        $this->assertTrue($policy->update($professor, $this->adaptacaoDe($this->planoDe(self::MINHA_PESSOA))));
        $this->assertTrue($policy->exportarPei($professor, $this->adaptacaoDe($this->planoDe(self::MINHA_PESSOA))));
        $this->assertFalse($policy->update($professor, $this->adaptacaoDe($this->planoDe(self::OUTRA_PESSOA))), 'plano de outro professor, sem turma em comum');
        $this->assertFalse($policy->update($professor, $this->adaptacaoDe(null)), 'sem plano de aula não há escopo');
        $this->assertFalse($policy->update($this->eu(['GerarPei:PlanoAula'], professor: true)->comPessoas([]), $this->adaptacaoDe($this->planoDe(self::MINHA_PESSOA))), 'professor sem pessoa vinculada');
    }

    public function test_pei_criar_exige_gerar_pei_e_exportar_exige_exportar_pei(): void
    {
        $policy = new PlanoAulaAdaptacaoPolicy;

        $this->assertFalse($policy->create($this->eu(['ExportarPei:PlanoAula'])));
        $this->assertTrue($policy->create($this->eu(['GerarPei:PlanoAula'], professor: true)), 'sem plano de aula informado ainda');
        $this->assertFalse($policy->create($this->eu(['GerarPei:PlanoAula'], professor: true), $this->planoDe(self::OUTRA_PESSOA)));
        $this->assertFalse($policy->exportarPei($this->eu(['GerarPei:PlanoAula']), $this->adaptacaoDe($this->planoDe(self::MINHA_PESSOA))));
        $this->assertFalse($policy->viewAny($this->eu([])));
    }

    // ---------- Relatório salvo, lead e encaminhamento ----------

    public function test_relatorio_salvo_so_o_autor_ou_quem_ve_todos(): void
    {
        $policy = new RelatorioSalvoPolicy;
        $meu = (new RelatorioSalvo)->forceFill(['criado_por_user_id' => self::EU]);
        $dele = (new RelatorioSalvo)->forceFill(['criado_por_user_id' => 99]);

        foreach (['view' => 'View', 'update' => 'Update', 'delete' => 'Delete', 'executar' => 'Executar'] as $metodo => $acao) {
            $permissao = "{$acao}:RelatorioSalvo";

            $this->assertTrue($policy->{$metodo}($this->eu([$permissao]), $meu), "{$metodo}: o autor");
            $this->assertFalse($policy->{$metodo}($this->eu([$permissao]), $dele), "{$metodo}: o relatório de outro");
            $this->assertTrue($policy->{$metodo}($this->eu([$permissao, 'VerTodos:RelatorioSalvo']), $dele), "{$metodo}: quem vê todos");
            $this->assertFalse($policy->{$metodo}($this->eu([]), $meu), "{$metodo}: sem a permissão nem o autor");
        }
    }

    public function test_lead_vem_do_consultor_dono_ou_de_quem_ve_todos(): void
    {
        $policy = new InteressadoPolicy;
        $semDono = (new Interessado)->forceFill(['usuario_id' => null]);
        $meu = (new Interessado)->forceFill(['usuario_id' => self::EU]);
        $dele = (new Interessado)->forceFill(['usuario_id' => 99]);

        $this->assertTrue($policy->view($this->eu(['View:Interessado']), $semDono), 'lead sem consultor é de todos');
        $this->assertTrue($policy->view($this->eu(['View:Interessado']), $meu));
        $this->assertFalse($policy->view($this->eu(['View:Interessado']), $dele));
        $this->assertTrue($policy->view($this->eu(['View:Interessado'], veTodosLeads: true), $dele));
        $this->assertFalse($policy->view($this->eu([], veTodosLeads: true), $dele), 'sem a permissão View nem quem vê todos');

        $this->assertTrue($policy->update($this->eu(['Update:Interessado']), $meu));
        $this->assertFalse($policy->update($this->eu(['Update:Interessado']), $dele));

        $this->assertTrue($policy->delete($this->eu(['Delete:Interessado']), $meu));
        $this->assertFalse($policy->delete($this->eu(['Delete:Interessado']), $semDono), 'excluir lead sem dono exige ver todos');
        $this->assertTrue($policy->delete($this->eu(['Delete:Interessado'], veTodosLeads: true), $dele));

        $this->assertTrue($policy->viewAll($this->eu([], veTodosLeads: true)));
        $this->assertFalse($policy->viewAll($this->eu()));
    }

    public function test_encaminhamento_o_responsavel_ve_e_atualiza_enquanto_aberto(): void
    {
        $policy = new ReuniaoEncaminhamentoPolicy;
        $aberto = (new ReuniaoEncaminhamento)->forceFill(['status' => StatusEncaminhamento::Aberto, 'responsavel_user_id' => self::EU]);
        $feito = (new ReuniaoEncaminhamento)->forceFill(['status' => StatusEncaminhamento::Feito, 'responsavel_user_id' => self::EU]);

        $this->assertTrue($policy->view($this->eu(), $aberto));
        $this->assertTrue($policy->update($this->eu(), $aberto));
        $this->assertFalse($policy->update($this->eu(), $feito), 'encaminhamento concluído não muda');
    }

    // ---------- Reunião: organizador, coordenação e sigilo ----------

    private function reuniao(TipoReuniao $tipo, int $organizador, bool $confidencial = false): Reuniao
    {
        return (new Reuniao)->forceFill(['tipo' => $tipo, 'organizador_user_id' => $organizador, 'confidencial' => $confidencial]);
    }

    public function test_reuniao_o_organizador_gerencia_e_a_coordenacao_so_se_nao_for_sigilosa(): void
    {
        $policy = new ReuniaoPolicy;

        $this->assertTrue($policy->gerenciar($this->eu(['Update:Reuniao']), $this->reuniao(TipoReuniao::Pedagogica, self::EU)));
        $this->assertFalse($policy->gerenciar($this->eu(['Update:Reuniao']), $this->reuniao(TipoReuniao::Pedagogica, 99)));
        $this->assertTrue($policy->gerenciar($this->eu(['Update:Reuniao', 'VerTodas:Reuniao']), $this->reuniao(TipoReuniao::Pedagogica, 99)));
        $this->assertFalse($policy->gerenciar($this->eu(['Update:Reuniao', 'VerTodas:Reuniao']), $this->reuniao(TipoReuniao::Pedagogica, 99, confidencial: true)), 'reunião sigilosa não abre à coordenação');
        $this->assertFalse($policy->gerenciar($this->eu([]), $this->reuniao(TipoReuniao::Pedagogica, self::EU)), 'sem Update:Reuniao nem o organizador');
    }

    public function test_reuniao_o_organizador_redige_a_ata_e_so_delibera_no_conselho_de_classe(): void
    {
        $policy = new ReuniaoPolicy;
        $organizador = $this->eu(['Update:Reuniao', 'Deliberar:Reuniao']);

        $this->assertTrue($policy->redigirAta($organizador, $this->reuniao(TipoReuniao::Pedagogica, self::EU)));
        $this->assertTrue($policy->deliberar($organizador, $this->reuniao(TipoReuniao::ConselhoClasse, self::EU)));
        $this->assertFalse($policy->deliberar($organizador, $this->reuniao(TipoReuniao::Pedagogica, self::EU)), 'só o conselho de classe delibera sobre alunos');
        $this->assertFalse($policy->deliberar($this->eu(['Update:Reuniao']), $this->reuniao(TipoReuniao::ConselhoClasse, self::EU)), 'sem Deliberar:Reuniao');
    }

    // ---------- Usuários: contas de super_admin são protegidas ----------

    /**
     * @param  list<string>  $permissoes
     * @param  list<string>  $papeis
     */
    private function conta(int $id, array $permissoes = [], array $papeis = []): UsuarioComPermissoes
    {
        return new UsuarioComPermissoes($permissoes, $id, false, $papeis);
    }

    public function test_usuario_so_super_admin_ve_edita_restaura_e_replica_conta_de_super_admin(): void
    {
        $policy = new UserPolicy;
        $alvoSuper = $this->conta(50, [], ['super_admin']);
        $alvoComum = $this->conta(51);

        foreach (['view' => 'View', 'update' => 'Update', 'restore' => 'Restore', 'replicate' => 'Replicate'] as $metodo => $acao) {
            $permissao = "{$acao}:User";
            $admin = $this->conta(1, [$permissao], ['admin']);
            $super = $this->conta(2, [$permissao], ['super_admin']);

            $this->assertTrue($policy->{$metodo}($admin, $alvoComum), "{$metodo}: conta comum");
            $this->assertFalse($policy->{$metodo}($admin, $alvoSuper), "{$metodo}: admin não toca em super_admin");
            $this->assertTrue($policy->{$metodo}($super, $alvoSuper), "{$metodo}: super_admin toca em super_admin");
            $this->assertFalse($policy->{$metodo}($this->conta(3, [], ['super_admin']), $alvoComum), "{$metodo}: sem a permissão nem super_admin");
        }
    }

    public function test_usuario_ninguem_exclui_a_propria_conta_nem_a_de_super_admin_sem_ser_um(): void
    {
        $policy = new UserPolicy;

        foreach (['delete' => 'Delete', 'forceDelete' => 'ForceDelete'] as $metodo => $acao) {
            $permissao = "{$acao}:User";
            $admin = $this->conta(1, [$permissao], ['admin']);
            $super = $this->conta(2, [$permissao], ['super_admin']);

            $this->assertFalse($policy->{$metodo}($admin, $this->conta(1)), "{$metodo}: a própria conta");
            $this->assertFalse($policy->{$metodo}($super, $this->conta(2)), "{$metodo}: nem o super_admin exclui a própria conta");
            $this->assertTrue($policy->{$metodo}($admin, $this->conta(51)), "{$metodo}: conta comum de outro");
            $this->assertFalse($policy->{$metodo}($admin, $this->conta(50, [], ['super_admin'])), "{$metodo}: admin não exclui super_admin");
            $this->assertTrue($policy->{$metodo}($super, $this->conta(50, [], ['super_admin'])), "{$metodo}: super_admin exclui outro super_admin");
        }
    }

    public function test_usuario_sem_alvo_a_regra_e_so_a_permissao(): void
    {
        $policy = new UserPolicy;

        $this->assertTrue($policy->view($this->conta(1, ['View:User'])));
        $this->assertTrue($policy->update($this->conta(1, ['Update:User'])));
        $this->assertFalse($policy->delete($this->conta(1, [])));
    }
}
