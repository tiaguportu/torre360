<?php

namespace Tests\Unit\Policies;

use App\Enums\StatusCancelamentoMatricula;
use App\Enums\StatusCenarioReajuste;
use App\Enums\StatusAtestadoAluno;
use App\Enums\StatusDocumentoAssinatura;
use App\Enums\StatusListaMaterial;
use App\Enums\StatusOrcamento;
use App\Enums\StatusParecerDescritivo;
use App\Enums\StatusProcessoFuncionario;
use App\Enums\StatusRequisicaoCompra;
use App\Enums\StatusReservaEspaco;
use App\Enums\StatusTratamentoPonto;
use App\Models\AtendimentoChamado;
use App\Models\AtestadoAluno;
use App\Models\CancelamentoMatricula;
use App\Models\CenarioReajuste;
use App\Models\ContaRecorrente;
use App\Models\DocumentoAssinatura;
use App\Models\Fatura;
use App\Models\Funcionario;
use App\Models\ListaMaterialEscolar;
use App\Models\Orcamento;
use App\Models\ParecerDescritivo;
use App\Models\Pessoa;
use App\Models\PlanoManutencao;
use App\Models\PontoTratamento;
use App\Models\Preceptoria;
use App\Models\ProcessoFuncionario;
use App\Models\RequisicaoCompra;
use App\Models\ReservaEspaco;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\UsuarioComPermissoes;
use Tests\TestCase;

/**
 * Policies cuja decisão depende do estado do registro (rascunho, enviado, publicado, cancelado...) ou de posse. Os
 * modelos são montados em memória, sem banco: o que se prova é a regra da policy, não o Eloquent.
 */
class PoliticasComRegraDeEstadoTest extends TestCase
{
    private const USUARIO = 7;

    private const OUTRO = 8;

    /**
     * @param  array<string, mixed>  $atributos
     * @return \Closure(): Model
     */
    private static function modelo(string $classe, array $atributos = [], array $relacoes = []): \Closure
    {
        return function () use ($classe, $atributos, $relacoes): Model {
            $modelo = (new $classe)->forceFill($atributos);
            foreach ($relacoes as $nome => $valor) {
                $modelo->setRelation($nome, $valor);
            }

            return $modelo;
        };
    }

    /**
     * @param  list<string>  $permissoes
     * @return array{0: string, 1: string, 2: \Closure, 3: list<string>, 4: bool}
     */
    private static function caso(string $policy, string $metodo, \Closure $modelo, array $permissoes, bool $esperado): array
    {
        return [$policy, $metodo, $modelo, $permissoes, $esperado];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: \Closure, 3: list<string>, 4: bool}>
     */
    public static function cenarios(): array
    {
        $u = self::USUARIO;
        $o = self::OUTRO;
        $req = fn (StatusRequisicaoCompra $s, int $solicitante = self::USUARIO) => self::modelo(RequisicaoCompra::class, ['status' => $s, 'solicitante_user_id' => $solicitante]);
        $orc = fn (StatusOrcamento $s) => self::modelo(Orcamento::class, ['status' => $s]);
        $cen = fn (StatusCenarioReajuste $s) => self::modelo(CenarioReajuste::class, ['status' => $s]);
        $lista = fn (StatusListaMaterial $s) => self::modelo(ListaMaterialEscolar::class, ['status' => $s]);
        $atestado = fn (StatusAtestadoAluno $s) => self::modelo(AtestadoAluno::class, ['status' => $s]);
        $processo = fn (StatusProcessoFuncionario $s) => self::modelo(ProcessoFuncionario::class, ['status' => $s]);
        $cancel = fn (StatusCancelamentoMatricula $s, array $mais = []) => self::modelo(CancelamentoMatricula::class, ['status' => $s] + $mais);
        $reserva = fn (StatusReservaEspaco $s, int $solicitante = self::USUARIO) => self::modelo(ReservaEspaco::class, ['status' => $s, 'solicitante_user_id' => $solicitante]);
        $parecer = fn (StatusParecerDescritivo $s) => self::modelo(ParecerDescritivo::class, ['status' => $s]);
        $tratamento = fn (StatusTratamentoPonto $s, int $solicitante) => self::modelo(PontoTratamento::class, ['status' => $s, 'solicitado_por_user_id' => $solicitante]);
        $chamado = fn (bool $restrito) => self::modelo(AtendimentoChamado::class, ['restrito' => $restrito]);
        $assinatura = fn (StatusDocumentoAssinatura $s, string $tipo = Pessoa::class) => self::modelo(DocumentoAssinatura::class, ['status' => $s, 'documentavel_type' => $tipo]);

        $casos = [
            // Requisição de compra: só o solicitante edita e envia, e só em rascunho
            'requisicao: dono edita rascunho' => self::caso('RequisicaoCompraPolicy', 'update', $req(StatusRequisicaoCompra::Rascunho), ['Update:RequisicaoCompra'], true),
            'requisicao: outro usuario nao edita' => self::caso('RequisicaoCompraPolicy', 'update', $req(StatusRequisicaoCompra::Rascunho, $o), ['Update:RequisicaoCompra'], false),
            'requisicao: nao edita depois de enviada' => self::caso('RequisicaoCompraPolicy', 'update', $req(StatusRequisicaoCompra::EmCotacao), ['Update:RequisicaoCompra'], false),
            'requisicao: sem permissao nao edita' => self::caso('RequisicaoCompraPolicy', 'update', $req(StatusRequisicaoCompra::Rascunho), [], false),
            'requisicao: dono envia rascunho' => self::caso('RequisicaoCompraPolicy', 'enviar', $req(StatusRequisicaoCompra::Rascunho), ['Create:RequisicaoCompra'], true),
            'requisicao: outro nao envia' => self::caso('RequisicaoCompraPolicy', 'enviar', $req(StatusRequisicaoCompra::Rascunho, $o), ['Create:RequisicaoCompra'], false),
            'requisicao: ja enviada nao envia de novo' => self::caso('RequisicaoCompraPolicy', 'enviar', $req(StatusRequisicaoCompra::EmCotacao), ['Create:RequisicaoCompra'], false),
            'requisicao: sem Create nao envia' => self::caso('RequisicaoCompraPolicy', 'enviar', $req(StatusRequisicaoCompra::Rascunho), [], false),
            'requisicao: compras cota em cotacao' => self::caso('RequisicaoCompraPolicy', 'cotar', $req(StatusRequisicaoCompra::EmCotacao), ['Cotar:RequisicaoCompra'], true),
            'requisicao: nao cota rascunho' => self::caso('RequisicaoCompraPolicy', 'cotar', $req(StatusRequisicaoCompra::Rascunho), ['Cotar:RequisicaoCompra'], false),
            'requisicao: sem Cotar nao cota' => self::caso('RequisicaoCompraPolicy', 'cotar', $req(StatusRequisicaoCompra::EmCotacao), [], false),
            'requisicao: dono cancela rascunho' => self::caso('RequisicaoCompraPolicy', 'cancelar', $req(StatusRequisicaoCompra::Rascunho), [], true),
            'requisicao: dono cancela aguardando aprovacao' => self::caso('RequisicaoCompraPolicy', 'cancelar', $req(StatusRequisicaoCompra::AguardandoAprovacao), [], true),
            'requisicao: recebida nao cancela' => self::caso('RequisicaoCompraPolicy', 'cancelar', $req(StatusRequisicaoCompra::Recebida), ['Cotar:RequisicaoCompra'], false),
            'requisicao: recebida em parte nao cancela' => self::caso('RequisicaoCompraPolicy', 'cancelar', $req(StatusRequisicaoCompra::RecebidaParcial), ['Cotar:RequisicaoCompra'], false),
            'requisicao: ja cancelada nao cancela' => self::caso('RequisicaoCompraPolicy', 'cancelar', $req(StatusRequisicaoCompra::Cancelada), ['Cotar:RequisicaoCompra'], false),
            'requisicao: terceiro sem Cotar nao cancela' => self::caso('RequisicaoCompraPolicy', 'cancelar', $req(StatusRequisicaoCompra::Rascunho, $o), [], false),
            'requisicao: compras cancela a de outro' => self::caso('RequisicaoCompraPolicy', 'cancelar', $req(StatusRequisicaoCompra::AguardandoAprovacao, $o), ['Cotar:RequisicaoCompra'], true),

            // Orçamento: só rascunho muda; aprovado só se revisa
            'orcamento: edita rascunho' => self::caso('OrcamentoPolicy', 'update', $orc(StatusOrcamento::Rascunho), ['Update:Orcamento'], true),
            'orcamento: aprovado nao edita' => self::caso('OrcamentoPolicy', 'update', $orc(StatusOrcamento::Aprovado), ['Update:Orcamento'], false),
            'orcamento: exclui rascunho' => self::caso('OrcamentoPolicy', 'delete', $orc(StatusOrcamento::Rascunho), ['Delete:Orcamento'], true),
            'orcamento: aprovado nao exclui' => self::caso('OrcamentoPolicy', 'delete', $orc(StatusOrcamento::Aprovado), ['Delete:Orcamento'], false),
            'orcamento: aprova rascunho' => self::caso('OrcamentoPolicy', 'aprovar', $orc(StatusOrcamento::Rascunho), ['Aprovar:Orcamento'], true),
            'orcamento: nao aprova duas vezes' => self::caso('OrcamentoPolicy', 'aprovar', $orc(StatusOrcamento::Aprovado), ['Aprovar:Orcamento'], false),
            'orcamento: revisa o aprovado' => self::caso('OrcamentoPolicy', 'revisar', $orc(StatusOrcamento::Aprovado), ['Update:Orcamento'], true),
            'orcamento: nao revisa rascunho' => self::caso('OrcamentoPolicy', 'revisar', $orc(StatusOrcamento::Rascunho), ['Update:Orcamento'], false),

            // Cenário de reajuste: simulação -> aprovado -> aplicado
            'cenario: edita simulacao' => self::caso('CenarioReajustePolicy', 'update', $cen(StatusCenarioReajuste::Simulacao), ['Update:CenarioReajuste'], true),
            'cenario: aprovado nao edita' => self::caso('CenarioReajustePolicy', 'update', $cen(StatusCenarioReajuste::Aprovado), ['Update:CenarioReajuste'], false),
            'cenario: exclui nao aplicado' => self::caso('CenarioReajustePolicy', 'delete', $cen(StatusCenarioReajuste::Aprovado), ['Delete:CenarioReajuste'], true),
            'cenario: aplicado nao exclui' => self::caso('CenarioReajustePolicy', 'delete', $cen(StatusCenarioReajuste::Aplicado), ['Delete:CenarioReajuste'], false),
            'cenario: aprova simulacao' => self::caso('CenarioReajustePolicy', 'aprovar', $cen(StatusCenarioReajuste::Simulacao), ['Aprovar:CenarioReajuste'], true),
            'cenario: nao aprova o aprovado' => self::caso('CenarioReajustePolicy', 'aprovar', $cen(StatusCenarioReajuste::Aprovado), ['Aprovar:CenarioReajuste'], false),
            'cenario: aplica o aprovado' => self::caso('CenarioReajustePolicy', 'aplicar', $cen(StatusCenarioReajuste::Aprovado), ['Aplicar:CenarioReajuste'], true),
            'cenario: nao aplica simulacao' => self::caso('CenarioReajustePolicy', 'aplicar', $cen(StatusCenarioReajuste::Simulacao), ['Aplicar:CenarioReajuste'], false),
            'cenario: desfaz o aplicado' => self::caso('CenarioReajustePolicy', 'desfazer', $cen(StatusCenarioReajuste::Aplicado), ['Aplicar:CenarioReajuste'], true),
            'cenario: nao desfaz o que nao foi aplicado' => self::caso('CenarioReajustePolicy', 'desfazer', $cen(StatusCenarioReajuste::Aprovado), ['Aplicar:CenarioReajuste'], false),
            'cenario: nao comunica simulacao' => self::caso('CenarioReajustePolicy', 'comunicar', $cen(StatusCenarioReajuste::Simulacao), ['Comunicar:CenarioReajuste'], false),
            'cenario: comunica o aprovado' => self::caso('CenarioReajustePolicy', 'comunicar', $cen(StatusCenarioReajuste::Aprovado), ['Comunicar:CenarioReajuste'], true),
            'cenario: comunica o aplicado' => self::caso('CenarioReajustePolicy', 'comunicar', $cen(StatusCenarioReajuste::Aplicado), ['Comunicar:CenarioReajuste'], true),

            // Conta recorrente e fatura
            'conta recorrente: reajusta a ativa' => self::caso('ContaRecorrentePolicy', 'reajustar', self::modelo(ContaRecorrente::class, ['is_active' => true]), ['Update:ContaRecorrente'], true),
            'conta recorrente: inativa nao reajusta' => self::caso('ContaRecorrentePolicy', 'reajustar', self::modelo(ContaRecorrente::class, ['is_active' => false]), ['Update:ContaRecorrente'], false),
            'fatura: sem pagamento nem gateway exclui' => self::caso('FaturaPolicy', 'delete', self::modelo(Fatura::class, [], ['transacoes' => collect()]), ['Delete:Fatura'], true),
            'fatura: com gateway nao exclui' => self::caso('FaturaPolicy', 'delete', self::modelo(Fatura::class, ['gateway_id' => 'pay_123'], ['transacoes' => collect()]), ['Delete:Fatura'], false),
            'fatura: com transacao nao exclui' => self::caso('FaturaPolicy', 'delete', self::modelo(Fatura::class, [], ['transacoes' => collect([new \stdClass])]), ['Delete:Fatura'], false),
            'fatura: sem permissao nao exclui' => self::caso('FaturaPolicy', 'delete', self::modelo(Fatura::class, [], ['transacoes' => collect()]), [], false),

            // Lista de material: publicada não muda
            'lista de material: edita itens do rascunho' => self::caso('ListaMaterialEscolarPolicy', 'editarItens', $lista(StatusListaMaterial::Rascunho), ['Update:ListaMaterialEscolar'], true),
            'lista de material: publicada nao edita itens' => self::caso('ListaMaterialEscolarPolicy', 'editarItens', $lista(StatusListaMaterial::Publicada), ['Update:ListaMaterialEscolar'], false),
            'lista de material: exclui rascunho' => self::caso('ListaMaterialEscolarPolicy', 'delete', $lista(StatusListaMaterial::Rascunho), ['Delete:ListaMaterialEscolar'], true),
            'lista de material: publicada nao exclui' => self::caso('ListaMaterialEscolarPolicy', 'delete', $lista(StatusListaMaterial::Publicada), ['Delete:ListaMaterialEscolar'], false),

            // Atestado e processo de funcionário: só enquanto vigente / em andamento
            'atestado: edita o vigente' => self::caso('AtestadoAlunoPolicy', 'update', $atestado(StatusAtestadoAluno::Vigente), ['Update:AtestadoAluno'], true),
            'atestado: cancelado nao edita' => self::caso('AtestadoAlunoPolicy', 'update', $atestado(StatusAtestadoAluno::Cancelado), ['Update:AtestadoAluno'], false),
            'atestado: cancela o vigente' => self::caso('AtestadoAlunoPolicy', 'cancelar', $atestado(StatusAtestadoAluno::Vigente), ['Cancelar:AtestadoAluno'], true),
            'atestado: nao cancela duas vezes' => self::caso('AtestadoAlunoPolicy', 'cancelar', $atestado(StatusAtestadoAluno::Cancelado), ['Cancelar:AtestadoAluno'], false),
            'processo: edita em andamento' => self::caso('ProcessoFuncionarioPolicy', 'update', $processo(StatusProcessoFuncionario::EmAndamento), ['Update:ProcessoFuncionario'], true),
            'processo: concluido nao edita' => self::caso('ProcessoFuncionarioPolicy', 'update', $processo(StatusProcessoFuncionario::Concluido), ['Update:ProcessoFuncionario'], false),
            'processo: conclui o em andamento' => self::caso('ProcessoFuncionarioPolicy', 'concluir', $processo(StatusProcessoFuncionario::EmAndamento), ['Concluir:ProcessoFuncionario'], true),
            'processo: cancelado nao conclui' => self::caso('ProcessoFuncionarioPolicy', 'concluir', $processo(StatusProcessoFuncionario::Cancelado), ['Concluir:ProcessoFuncionario'], false),

            // Plano de manutenção
            'manutencao: plano ativo gera ordem' => self::caso('PlanoManutencaoPolicy', 'gerarOrdem', self::modelo(PlanoManutencao::class, ['ativo' => true]), ['GerarOrdem:PlanoManutencao'], true),
            'manutencao: plano inativo nao gera ordem' => self::caso('PlanoManutencaoPolicy', 'gerarOrdem', self::modelo(PlanoManutencao::class, ['ativo' => false]), ['GerarOrdem:PlanoManutencao'], false),
            'manutencao: plano ativo registra execucao' => self::caso('PlanoManutencaoPolicy', 'registrarExecucao', self::modelo(PlanoManutencao::class, ['ativo' => true]), ['RegistrarExecucao:PlanoManutencao'], true),
            'manutencao: plano inativo nao registra execucao' => self::caso('PlanoManutencaoPolicy', 'registrarExecucao', self::modelo(PlanoManutencao::class, ['ativo' => false]), ['RegistrarExecucao:PlanoManutencao'], false),

            // Cancelamento de matrícula: solicitado -> aprovado -> concluído, com devolução
            'cancelamento: aprova o solicitado' => self::caso('CancelamentoMatriculaPolicy', 'aprovar', $cancel(StatusCancelamentoMatricula::Solicitado), ['Aprovar:CancelamentoMatricula'], true),
            'cancelamento: nao aprova o aprovado' => self::caso('CancelamentoMatriculaPolicy', 'aprovar', $cancel(StatusCancelamentoMatricula::Aprovado), ['Aprovar:CancelamentoMatricula'], false),
            'cancelamento: anexa distrato ao aprovado' => self::caso('CancelamentoMatriculaPolicy', 'anexarDistrato', $cancel(StatusCancelamentoMatricula::Aprovado), ['Aprovar:CancelamentoMatricula'], true),
            'cancelamento: nao anexa distrato ao solicitado' => self::caso('CancelamentoMatriculaPolicy', 'anexarDistrato', $cancel(StatusCancelamentoMatricula::Solicitado), ['Aprovar:CancelamentoMatricula'], false),
            'cancelamento: encerra o solicitado' => self::caso('CancelamentoMatriculaPolicy', 'encerrar', $cancel(StatusCancelamentoMatricula::Solicitado), ['Aprovar:CancelamentoMatricula'], true),
            'cancelamento: encerra o aprovado' => self::caso('CancelamentoMatriculaPolicy', 'encerrar', $cancel(StatusCancelamentoMatricula::Aprovado), ['Aprovar:CancelamentoMatricula'], true),
            'cancelamento: nao encerra o concluido' => self::caso('CancelamentoMatriculaPolicy', 'encerrar', $cancel(StatusCancelamentoMatricula::Concluido), ['Aprovar:CancelamentoMatricula'], false),
            'cancelamento: nao encerra o recusado' => self::caso('CancelamentoMatriculaPolicy', 'encerrar', $cancel(StatusCancelamentoMatricula::Recusado), ['Aprovar:CancelamentoMatricula'], false),
            'cancelamento: conclui o aprovado' => self::caso('CancelamentoMatriculaPolicy', 'concluir', $cancel(StatusCancelamentoMatricula::Aprovado), ['Concluir:CancelamentoMatricula'], true),
            'cancelamento: nao conclui o solicitado' => self::caso('CancelamentoMatriculaPolicy', 'concluir', $cancel(StatusCancelamentoMatricula::Solicitado), ['Concluir:CancelamentoMatricula'], false),
            'cancelamento: registra devolucao pendente' => self::caso('CancelamentoMatriculaPolicy', 'registrarDevolucao', $cancel(StatusCancelamentoMatricula::Concluido, ['valor_a_devolver' => 150]), ['Concluir:CancelamentoMatricula'], true),
            'cancelamento: sem valor a devolver nao registra' => self::caso('CancelamentoMatriculaPolicy', 'registrarDevolucao', $cancel(StatusCancelamentoMatricula::Concluido, ['valor_a_devolver' => 0]), ['Concluir:CancelamentoMatricula'], false),
            'cancelamento: devolucao ja feita nao registra' => self::caso('CancelamentoMatriculaPolicy', 'registrarDevolucao', $cancel(StatusCancelamentoMatricula::Concluido, ['valor_a_devolver' => 150, 'devolucao_realizada_em' => '2026-10-01 10:00:00']), ['Concluir:CancelamentoMatricula'], false),
            'cancelamento: devolucao so depois de concluido' => self::caso('CancelamentoMatriculaPolicy', 'registrarDevolucao', $cancel(StatusCancelamentoMatricula::Aprovado, ['valor_a_devolver' => 150]), ['Concluir:CancelamentoMatricula'], false),

            // Reserva de espaço: dono ou aprovador; cancelada não muda
            'reserva: dono edita a solicitada' => self::caso('ReservaEspacoPolicy', 'update', $reserva(StatusReservaEspaco::Solicitada), ['Update:ReservaEspaco'], true),
            'reserva: outro nao edita' => self::caso('ReservaEspacoPolicy', 'update', $reserva(StatusReservaEspaco::Solicitada, $o), ['Update:ReservaEspaco'], false),
            'reserva: aprovador edita a de outro' => self::caso('ReservaEspacoPolicy', 'update', $reserva(StatusReservaEspaco::Solicitada, $o), ['Update:ReservaEspaco', 'Confirmar:ReservaEspaco'], true),
            'reserva: cancelada nao edita' => self::caso('ReservaEspacoPolicy', 'update', $reserva(StatusReservaEspaco::Cancelada), ['Update:ReservaEspaco'], false),
            'reserva: aprovador confirma a solicitada' => self::caso('ReservaEspacoPolicy', 'confirmar', $reserva(StatusReservaEspaco::Solicitada), ['Confirmar:ReservaEspaco'], true),
            'reserva: nao confirma a ja confirmada' => self::caso('ReservaEspacoPolicy', 'confirmar', $reserva(StatusReservaEspaco::Confirmada), ['Confirmar:ReservaEspaco'], false),
            'reserva: aprovador cancela a de outro' => self::caso('ReservaEspacoPolicy', 'cancelar', $reserva(StatusReservaEspaco::Confirmada, $o), ['Confirmar:ReservaEspaco'], true),
            'reserva: dono cancela a propria' => self::caso('ReservaEspacoPolicy', 'cancelar', $reserva(StatusReservaEspaco::Solicitada), ['Update:ReservaEspaco'], true),
            'reserva: outro sem aprovacao nao cancela' => self::caso('ReservaEspacoPolicy', 'cancelar', $reserva(StatusReservaEspaco::Solicitada, $o), ['Update:ReservaEspaco'], false),
            'reserva: ja cancelada nao cancela de novo' => self::caso('ReservaEspacoPolicy', 'cancelar', $reserva(StatusReservaEspaco::Cancelada), ['Confirmar:ReservaEspaco', 'Update:ReservaEspaco'], false),

            // Parecer descritivo: publicado só se reabre
            'parecer: exclui rascunho' => self::caso('ParecerDescritivoPolicy', 'delete', $parecer(StatusParecerDescritivo::Rascunho), ['Delete:ParecerDescritivo'], true),
            'parecer: publicado nao exclui' => self::caso('ParecerDescritivoPolicy', 'delete', $parecer(StatusParecerDescritivo::Publicado), ['Delete:ParecerDescritivo'], false),
            'parecer: publica o em revisao' => self::caso('ParecerDescritivoPolicy', 'publicar', $parecer(StatusParecerDescritivo::EmRevisao), ['Publicar:ParecerDescritivo'], true),
            'parecer: nao publica o ja publicado' => self::caso('ParecerDescritivoPolicy', 'publicar', $parecer(StatusParecerDescritivo::Publicado), ['Publicar:ParecerDescritivo'], false),
            'parecer: reabre o publicado' => self::caso('ParecerDescritivoPolicy', 'reabrir', $parecer(StatusParecerDescritivo::Publicado), ['Publicar:ParecerDescritivo'], true),
            'parecer: nao reabre o rascunho' => self::caso('ParecerDescritivoPolicy', 'reabrir', $parecer(StatusParecerDescritivo::Rascunho), ['Publicar:ParecerDescritivo'], false),
            'parecer: sem Publicar ninguem publica' => self::caso('ParecerDescritivoPolicy', 'publicar', $parecer(StatusParecerDescritivo::EmRevisao), ['Update:ParecerDescritivo'], false),

            // Tratamento de ponto: quem pediu não aprova o próprio pedido
            'tratamento: gestor aprova pedido de outro' => self::caso('PontoTratamentoPolicy', 'aprovar', $tratamento(StatusTratamentoPonto::Pendente, $o), ['Aprovar:PontoTratamento'], true),
            'tratamento: ninguem aprova o proprio pedido' => self::caso('PontoTratamentoPolicy', 'aprovar', $tratamento(StatusTratamentoPonto::Pendente, $u), ['Aprovar:PontoTratamento'], false),
            'tratamento: ja aprovado nao aprova de novo' => self::caso('PontoTratamentoPolicy', 'aprovar', $tratamento(StatusTratamentoPonto::Aprovado, $o), ['Aprovar:PontoTratamento'], false),
            'tratamento: gestor recusa pendente' => self::caso('PontoTratamentoPolicy', 'recusar', $tratamento(StatusTratamentoPonto::Pendente, $o), ['Aprovar:PontoTratamento'], true),
            'tratamento: nao recusa o ja decidido' => self::caso('PontoTratamentoPolicy', 'recusar', $tratamento(StatusTratamentoPonto::Recusado, $o), ['Aprovar:PontoTratamento'], false),
            'tratamento: sem Aprovar nao recusa' => self::caso('PontoTratamentoPolicy', 'recusar', $tratamento(StatusTratamentoPonto::Pendente, $o), [], false),

            // Chamado restrito
            'chamado: ve o comum' => self::caso('AtendimentoChamadoPolicy', 'view', $chamado(false), ['View:AtendimentoChamado'], true),
            'chamado: nao ve o restrito' => self::caso('AtendimentoChamadoPolicy', 'view', $chamado(true), ['View:AtendimentoChamado'], false),
            'chamado: ve o restrito com permissao' => self::caso('AtendimentoChamadoPolicy', 'view', $chamado(true), ['View:AtendimentoChamado', 'VerRestritos:AtendimentoChamado'], true),
            'chamado: nao edita o restrito' => self::caso('AtendimentoChamadoPolicy', 'update', $chamado(true), ['Update:AtendimentoChamado'], false),
            'chamado: edita o restrito com permissao' => self::caso('AtendimentoChamadoPolicy', 'update', $chamado(true), ['Update:AtendimentoChamado', 'VerRestritos:AtendimentoChamado'], true),

            // Assinatura de documento: documento de RH exige também a permissão de RH
            'assinatura: ve links do enviado' => self::caso('DocumentoAssinaturaPolicy', 'verLinks', $assinatura(StatusDocumentoAssinatura::Enviado), ['Enviar:DocumentoAssinatura'], true),
            'assinatura: rascunho nao tem links' => self::caso('DocumentoAssinaturaPolicy', 'verLinks', $assinatura(StatusDocumentoAssinatura::Rascunho), ['Enviar:DocumentoAssinatura'], false),
            'assinatura: documento de RH sem permissao de RH' => self::caso('DocumentoAssinaturaPolicy', 'verLinks', $assinatura(StatusDocumentoAssinatura::Enviado, Funcionario::class), ['Enviar:DocumentoAssinatura'], false),
            'assinatura: documento de RH com permissao de RH' => self::caso('DocumentoAssinaturaPolicy', 'verLinks', $assinatura(StatusDocumentoAssinatura::Enviado, Funcionario::class), ['Enviar:DocumentoAssinatura', 'View:ProcessoFuncionario'], true),
            'assinatura: cancela o em andamento' => self::caso('DocumentoAssinaturaPolicy', 'cancelar', $assinatura(StatusDocumentoAssinatura::Enviado), ['Cancelar:DocumentoAssinatura'], true),
            'assinatura: assinado nao cancela' => self::caso('DocumentoAssinaturaPolicy', 'cancelar', $assinatura(StatusDocumentoAssinatura::Assinado), ['Cancelar:DocumentoAssinatura'], false),
            'assinatura: RH sem permissao nao cancela' => self::caso('DocumentoAssinaturaPolicy', 'cancelar', $assinatura(StatusDocumentoAssinatura::Enviado, Funcionario::class), ['Cancelar:DocumentoAssinatura'], false),

            // Preceptoria: quem pode agendar também lista; quem exclui restaura
            'preceptoria: ViewAny lista' => self::caso('PreceptoriaPolicy', 'viewAny', self::modelo(Preceptoria::class), ['ViewAny:Preceptoria'], true),
            'preceptoria: quem agenda tambem lista' => self::caso('PreceptoriaPolicy', 'viewAny', self::modelo(Preceptoria::class), ['Agendar:Preceptoria'], true),
            'preceptoria: sem nada nao lista' => self::caso('PreceptoriaPolicy', 'viewAny', self::modelo(Preceptoria::class), [], false),
            'preceptoria: Restore restaura' => self::caso('PreceptoriaPolicy', 'restore', self::modelo(Preceptoria::class), ['Restore:Preceptoria'], true),
            'preceptoria: quem exclui restaura' => self::caso('PreceptoriaPolicy', 'restore', self::modelo(Preceptoria::class), ['Delete:Preceptoria'], true),
            'preceptoria: sem Delete nem Restore nao restaura' => self::caso('PreceptoriaPolicy', 'restore', self::modelo(Preceptoria::class), ['Update:Preceptoria'], false),
            'preceptoria: DeleteAny restaura em lote' => self::caso('PreceptoriaPolicy', 'restoreAny', self::modelo(Preceptoria::class), ['DeleteAny:Preceptoria'], true),
            'preceptoria: sem DeleteAny nao restaura em lote' => self::caso('PreceptoriaPolicy', 'restoreAny', self::modelo(Preceptoria::class), ['Delete:Preceptoria'], false),
        ];

        return array_map(fn (array $c) => $c, $casos);
    }

    /**
     * @param  list<string>  $permissoes
     */
    #[DataProvider('cenarios')]
    public function test_a_decisao_segue_o_estado_do_registro(string $policy, string $metodo, \Closure $modelo, array $permissoes, bool $esperado): void
    {
        $classe = "App\\Policies\\{$policy}";
        $decisao = (new $classe)->{$metodo}(new UsuarioComPermissoes($permissoes, self::USUARIO), $modelo());

        $this->assertSame($esperado, $decisao);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: class-string<Model>|null}>
     */
    public static function acoesNegadasATodos(): array
    {
        return [
            'requisicao: nunca exclui' => ['RequisicaoCompraPolicy', 'delete', RequisicaoCompra::class],
            'atestado: nunca exclui' => ['AtestadoAlunoPolicy', 'delete', AtestadoAluno::class],
            'processo: nunca exclui' => ['ProcessoFuncionarioPolicy', 'delete', ProcessoFuncionario::class],
            'cancelamento: nunca edita' => ['CancelamentoMatriculaPolicy', 'update', CancelamentoMatricula::class],
            'cancelamento: nunca exclui' => ['CancelamentoMatriculaPolicy', 'delete', CancelamentoMatricula::class],
            'ponto: acordo de banco de horas nunca exclui' => ['PontoAcordoBancoHorasPolicy', 'delete', \App\Models\PontoAcordoBancoHoras::class],
            'ponto: configuracao nunca exclui' => ['PontoConfiguracaoPolicy', 'delete', \App\Models\PontoConfiguracao::class],
            'ponto: jornada nunca exclui' => ['PontoJornadaPolicy', 'delete', \App\Models\PontoJornada::class],
            'ponto: registro nunca se cria a mao' => ['PontoRegistroPolicy', 'create', null],
            'ponto: registro nunca se edita' => ['PontoRegistroPolicy', 'update', \App\Models\PontoRegistro::class],
            'ponto: registro nunca se exclui' => ['PontoRegistroPolicy', 'delete', \App\Models\PontoRegistro::class],
            'ponto: tratamento nunca se edita' => ['PontoTratamentoPolicy', 'update', PontoTratamento::class],
            'ponto: tratamento nunca se exclui' => ['PontoTratamentoPolicy', 'delete', PontoTratamento::class],
            'denuncia: nunca se cria' => ['DenunciaPolicy', 'create', null],
            'denuncia: nunca se edita' => ['DenunciaPolicy', 'update', \App\Models\Denuncia::class],
            'denuncia: nunca se exclui' => ['DenunciaPolicy', 'delete', \App\Models\Denuncia::class],
            'denuncia: nunca se exclui em lote' => ['DenunciaPolicy', 'deleteAny', null],
            'denuncia: nunca se restaura' => ['DenunciaPolicy', 'restore', \App\Models\Denuncia::class],
            'denuncia: nunca se apaga de vez' => ['DenunciaPolicy', 'forceDelete', \App\Models\Denuncia::class],
            'reuniao: nunca se exclui' => ['ReuniaoPolicy', 'delete', \App\Models\Reuniao::class],
            'reuniao: nunca se exclui em lote' => ['ReuniaoPolicy', 'deleteAny', null],
            'reuniao aluno: nunca se cria' => ['ReuniaoAlunoPolicy', 'create', null],
            'reuniao aluno: nunca se exclui' => ['ReuniaoAlunoPolicy', 'delete', \App\Models\ReuniaoAluno::class],
            'reuniao aluno: nunca se exclui em lote' => ['ReuniaoAlunoPolicy', 'deleteAny', null],
            'encaminhamento: nunca se exclui' => ['ReuniaoEncaminhamentoPolicy', 'delete', \App\Models\ReuniaoEncaminhamento::class],
            'encaminhamento: nunca se exclui em lote' => ['ReuniaoEncaminhamentoPolicy', 'deleteAny', null],
            'autoavaliacao: nunca se exclui' => ['AutoavaliacaoDocentePolicy', 'delete', \App\Models\AutoavaliacaoDocente::class],
        ];
    }

    /**
     * @param  class-string<Model>|null  $classeDoModelo
     */
    #[DataProvider('acoesNegadasATodos')]
    public function test_acoes_que_nem_quem_tem_todas_as_permissoes_pode_fazer(string $policy, string $metodo, ?string $classeDoModelo): void
    {
        $classe = "App\\Policies\\{$policy}";
        $argumentos = $classeDoModelo ? [new $classeDoModelo] : [];

        $this->assertFalse((new $classe)->{$metodo}(UsuarioComPermissoes::comTudo(self::USUARIO), ...$argumentos));
    }
}
