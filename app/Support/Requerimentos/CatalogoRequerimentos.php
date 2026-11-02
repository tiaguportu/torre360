<?php

declare(strict_types=1);

namespace App\Support\Requerimentos;

/**
 * Catálogo dos requerimentos que a família faz pelo Portal (Ondas 80 e 34). Os campos ficam em código de propósito: abrir a
 * edição do formulário ao administrador é a parte cara da Onda 80 e fica para depois.
 *
 * A segunda via de declaração e de histórico continua no fluxo de *Solicitações de documentos* e não entra aqui.
 */
final class CatalogoRequerimentos
{
    /**
     * @return array<string, TipoRequerimento>
     */
    public static function todos(): array
    {
        $tipos = [
            new TipoRequerimento(
                chave: 'atualizacao_cadastral',
                nome: 'Atualizar telefone, e-mail ou endereço',
                orientacao: 'Informe só o que mudou. A secretaria confere e atualiza o seu cadastro; você é avisado quando terminar.',
                campos: [
                    ['nome' => 'telefone', 'rotulo' => 'Novo telefone', 'tipo' => 'text'],
                    ['nome' => 'email', 'rotulo' => 'Novo e-mail', 'tipo' => 'text'],
                    ['nome' => 'endereco', 'rotulo' => 'Novo endereço completo', 'tipo' => 'textarea'],
                ],
                prazoDias: 5,
                tratador: AtualizacaoCadastral::class,
            ),
            new TipoRequerimento(
                chave: 'atestado_aluno',
                nome: 'Enviar atestado médico do aluno',
                orientacao: 'Anexe a foto ou o PDF do atestado. A secretaria registra o período e justifica as faltas; se a escola abonar, as faltas deixam de contar na frequência.',
                campos: [
                    ['nome' => 'inicio', 'rotulo' => 'Primeiro dia do atestado', 'tipo' => 'date', 'obrigatorio' => true],
                    ['nome' => 'fim', 'rotulo' => 'Último dia do atestado', 'tipo' => 'date', 'obrigatorio' => true],
                    ['nome' => 'comprovante', 'rotulo' => 'Atestado (PDF ou foto)', 'tipo' => 'file', 'obrigatorio' => true],
                    ['nome' => 'observacao', 'rotulo' => 'Observação', 'tipo' => 'textarea'],
                ],
                prazoDias: 2,
                exigeMatricula: true,
                tratador: EnvioDeAtestado::class,
            ),
            new TipoRequerimento(
                chave: 'autorizacao_retirada',
                nome: 'Autorizar outra pessoa a retirar o aluno',
                orientacao: 'Informe quem pode buscar o aluno. A secretaria confirma com você antes de liberar. Quem busca precisa apresentar documento com foto.',
                campos: [
                    ['nome' => 'nome_autorizado', 'rotulo' => 'Nome completo de quem vai retirar', 'tipo' => 'text', 'obrigatorio' => true],
                    ['nome' => 'documento', 'rotulo' => 'Documento (RG ou CPF)', 'tipo' => 'text', 'obrigatorio' => true],
                    ['nome' => 'parentesco', 'rotulo' => 'Parentesco ou vínculo', 'tipo' => 'text', 'obrigatorio' => true],
                    ['nome' => 'validade', 'rotulo' => 'Vale até (deixe em branco se for permanente)', 'tipo' => 'date'],
                    ['nome' => 'observacao', 'rotulo' => 'Observação', 'tipo' => 'textarea'],
                ],
                prazoDias: 2,
                exigeMatricula: true,
            ),
            new TipoRequerimento(
                chave: 'troca_turma_turno',
                nome: 'Pedir troca de turma ou de turno',
                orientacao: 'O pedido depende de vaga. A secretaria responde com a possibilidade e, se houver, orienta os próximos passos.',
                campos: [
                    ['nome' => 'tipo_troca', 'rotulo' => 'O que você quer trocar', 'tipo' => 'select', 'obrigatorio' => true, 'opcoes' => ['turma' => 'Turma', 'turno' => 'Turno']],
                    ['nome' => 'desejado', 'rotulo' => 'Para qual turma ou turno', 'tipo' => 'text', 'obrigatorio' => true],
                    ['nome' => 'motivo', 'rotulo' => 'Motivo', 'tipo' => 'textarea', 'obrigatorio' => true],
                ],
                prazoDias: 10,
                exigeMatricula: true,
            ),
            new TipoRequerimento(
                chave: 'troca_responsavel_financeiro',
                nome: 'Trocar o responsável financeiro',
                orientacao: 'A troca altera o contrato e exige assinatura. A secretaria entra em contato para explicar os documentos necessários.',
                campos: [
                    ['nome' => 'novo_nome', 'rotulo' => 'Nome completo do novo responsável', 'tipo' => 'text', 'obrigatorio' => true],
                    ['nome' => 'novo_cpf', 'rotulo' => 'CPF do novo responsável', 'tipo' => 'text', 'obrigatorio' => true],
                    ['nome' => 'novo_telefone', 'rotulo' => 'Telefone', 'tipo' => 'text'],
                    ['nome' => 'novo_email', 'rotulo' => 'E-mail', 'tipo' => 'text'],
                    ['nome' => 'motivo', 'rotulo' => 'Motivo', 'tipo' => 'textarea', 'obrigatorio' => true],
                ],
                prazoDias: 5,
                exigeMatricula: true,
            ),
            new TipoRequerimento(
                chave: 'pedido_titular',
                nome: 'Direitos sobre os meus dados (LGPD)',
                orientacao: 'Você pode pedir confirmação do tratamento, acesso, correção, anonimização, portabilidade, eliminação, informação sobre compartilhamento ou a revogação de um consentimento. A resposta sai em até '.(int) config('atendimento.prazo_pedido_titular_dias', 15).' dias e só o encarregado de dados e a direção veem o seu pedido.',
                campos: [
                    ['nome' => 'tipo_pedido', 'rotulo' => 'O que você deseja', 'tipo' => 'select', 'obrigatorio' => true, 'opcoes' => [
                        'confirmacao' => 'Confirmar se a escola trata meus dados',
                        'acesso' => 'Ter acesso aos meus dados',
                        'correcao' => 'Corrigir dados incompletos ou desatualizados',
                        'anonimizacao' => 'Anonimizar ou bloquear dados desnecessários',
                        'portabilidade' => 'Receber meus dados para levar a outro fornecedor',
                        'eliminacao' => 'Eliminar dados tratados com o meu consentimento',
                        'compartilhamento' => 'Saber com quem meus dados foram compartilhados',
                        'revogacao' => 'Revogar um consentimento',
                    ]],
                    ['nome' => 'descricao', 'rotulo' => 'Descreva o seu pedido', 'tipo' => 'textarea', 'obrigatorio' => true],
                ],
                prazoDias: (int) config('atendimento.prazo_pedido_titular_dias', 15),
                diasUteis: false,
                restrito: true,
            ),
        ];

        $porChave = [];

        foreach ($tipos as $tipo) {
            $porChave[$tipo->chave] = $tipo;
        }

        return $porChave;
    }

    public static function tipo(string $chave): ?TipoRequerimento
    {
        return self::todos()[$chave] ?? null;
    }
}
