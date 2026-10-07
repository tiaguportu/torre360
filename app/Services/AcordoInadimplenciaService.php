<?php

namespace App\Services;

use App\Enums\StatusAcordoInadimplencia;
use App\Models\AcordoInadimplencia;
use App\Models\AcordoParcela;
use App\Models\Fatura;
use App\Models\Matricula;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcordoInadimplenciaService
{
    /**
     * Localiza todas as faturas em atraso elegíveis para renegociação de uma matrícula.
     */
    public function localizarFaturasVencidas(int $matriculaId): Collection
    {
        return Fatura::query()
            ->whereHas('contrato', fn ($q) => $q->where('matricula_id', $matriculaId))
            ->whereIn('status', ['atrasado', 'pendente'])
            ->where('vencimento', '<', now()->toDateString())
            ->with(['contrato', 'itens'])
            ->orderBy('vencimento')
            ->get();
    }

    /**
     * Simula o plano de parcelamento e calcula descontos e valores de cada parcela.
     */
    public function simularAcordo(
        float $valorOriginal,
        float $valorMulta,
        float $valorJuros,
        float $percentualDesconto,
        float $valorEntrada,
        int $quantidadeParcelas,
        int $diaVencimento,
        ?string $primeiroVencimento = null
    ): array {
        $quantidadeParcelas = max(1, $quantidadeParcelas);
        $totalBruto = $valorOriginal + $valorMulta + $valorJuros;

        // O desconto incide preferencialmente sobre encargos moratórios (juros e multa)
        $baseDesconto = ($valorMulta + $valorJuros) > 0 ? ($valorMulta + $valorJuros) : $totalBruto;
        $valorDesconto = round($baseDesconto * ($percentualDesconto / 100), 2);
        $valorLiquidoAcordo = max(1, round($totalBruto - $valorDesconto, 2));

        $valorEntrada = min($valorLiquidoAcordo, max(0, $valorEntrada));
        $saldoParcelar = max(0, $valorLiquidoAcordo - $valorEntrada);

        $valorParcela = $quantidadeParcelas > 0 ? round($saldoParcelar / $quantidadeParcelas, 2) : 0;

        // Ajuste de centavos na última parcela para fechar o valor exato
        $somaParcelas = $valorParcela * $quantidadeParcelas;
        $diferencaCentavos = round($saldoParcelar - $somaParcelas, 2);

        // Os demais vencimentos caem sempre entre os dias 1 e 28, para existirem em todos os meses.
        $diaParcelas = max(1, min(28, $diaVencimento));

        $dtPrimeiroVenc = $primeiroVencimento
            ? Carbon::parse($primeiroVencimento)->startOfDay()
            : now()->startOfDay()->addMonthNoOverflow()->day($diaParcelas);

        // Âncora no dia 1 do mês da primeira parcela: somar meses a partir de um dia 29–31 estourava o mês
        // (31/01 + 1 mês = 03/03), pulando fevereiro e repetindo março.
        $mesBase = $dtPrimeiroVenc->copy()->startOfMonth();

        $parcelasProjetadas = [];
        if ($valorEntrada > 0) {
            $parcelasProjetadas[] = [
                'numero_parcela' => 0,
                'descricao' => 'Entrada Facilitada',
                'valor' => $valorEntrada,
                'vencimento' => now()->addDays(2)->toDateString(),
            ];
        }

        for ($i = 1; $i <= $quantidadeParcelas; $i++) {
            $valorItem = $valorParcela;
            if ($i === $quantidadeParcelas) {
                $valorItem += $diferencaCentavos;
            }

            // A 1ª parcela vence na data informada (a mesma que o termo imprime como "primeiro vencimento").
            $venc = $i === 1
                ? $dtPrimeiroVenc->copy()
                : $mesBase->copy()->addMonthsNoOverflow($i - 1)->day($diaParcelas);

            $parcelasProjetadas[] = [
                'numero_parcela' => $i,
                'descricao' => "Parcela {$i}/{$quantidadeParcelas}",
                'valor' => round($valorItem, 2),
                'vencimento' => $venc->toDateString(),
            ];
        }

        return [
            'valor_original_total' => $valorOriginal,
            'valor_multa_original' => $valorMulta,
            'valor_juros_original' => $valorJuros,
            'total_bruto' => $totalBruto,
            'percentual_desconto_concedido' => $percentualDesconto,
            'valor_desconto' => $valorDesconto,
            'valor_total_acordo' => $valorLiquidoAcordo,
            'valor_entrada' => $valorEntrada,
            'quantidade_parcelas' => $quantidadeParcelas,
            'valor_parcela' => $valorParcela,
            'dia_vencimento_parcelas' => $diaVencimento,
            'primeiro_vencimento' => $dtPrimeiroVenc->toDateString(),
            'cronograma_parcelas' => $parcelasProjetadas,
        ];
    }

    /**
     * Efetiva a criação de um Acordo de Inadimplência com geração de parcelas e minuta jurídica.
     */
    public function criarAcordo(array $dados, User $criador): AcordoInadimplencia
    {
        return DB::transaction(function () use ($dados, $criador) {
            $simulacao = $this->simularAcordo(
                (float) ($dados['valor_original_total'] ?? 0),
                (float) ($dados['valor_multa_original'] ?? 0),
                (float) ($dados['valor_juros_original'] ?? 0),
                (float) ($dados['percentual_desconto_concedido'] ?? 0),
                (float) ($dados['valor_entrada'] ?? 0),
                (int) ($dados['quantidade_parcelas'] ?? 1),
                (int) ($dados['dia_vencimento_parcelas'] ?? 10),
                $dados['primeiro_vencimento'] ?? null
            );

            $matricula = Matricula::with(['pessoa', 'turma'])->findOrFail($dados['matricula_id']);
            $contratoId = $dados['contrato_id'] ?? $matricula->contrato?->id;

            $acordo = AcordoInadimplencia::create([
                'codigo' => AcordoInadimplencia::gerarCodigo(),
                'contrato_id' => $contratoId,
                'matricula_id' => $matricula->id,
                'responsavel_pessoa_id' => $dados['responsavel_pessoa_id'],
                'criado_por_user_id' => $criador->id,
                'valor_original_total' => $simulacao['valor_original_total'],
                'valor_multa_original' => $simulacao['valor_multa_original'],
                'valor_juros_original' => $simulacao['valor_juros_original'],
                'quantidade_faturas_originais' => count($dados['faturas_originais_ids'] ?? []),
                'faturas_originais_ids' => $dados['faturas_originais_ids'] ?? [],
                'percentual_desconto_concedido' => $simulacao['percentual_desconto_concedido'],
                'valor_desconto' => $simulacao['valor_desconto'],
                'valor_total_acordo' => $simulacao['valor_total_acordo'],
                'valor_entrada' => $simulacao['valor_entrada'],
                'data_vencimento_entrada' => $simulacao['valor_entrada'] > 0 ? now()->addDays(2) : null,
                'quantidade_parcelas' => $simulacao['quantidade_parcelas'],
                'valor_parcela' => $simulacao['valor_parcela'],
                'dia_vencimento_parcelas' => $simulacao['dia_vencimento_parcelas'],
                'primeiro_vencimento' => $simulacao['primeiro_vencimento'],
                'token_publico' => AcordoInadimplencia::gerarTokenPublico(),
                'status' => StatusAcordoInadimplencia::AguardandoAceite,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            // Gera a minuta jurídica do Termo de Confissão de Dívida
            $acordo->update([
                'termo_confissao_texto' => $this->gerarMinutaConfissaoDivida($acordo),
            ]);

            // Cria as parcelas filhas
            foreach ($simulacao['cronograma_parcelas'] as $p) {
                AcordoParcela::create([
                    'acordo_inadimplencia_id' => $acordo->id,
                    'numero_parcela' => $p['numero_parcela'],
                    'valor' => $p['valor'],
                    'data_vencimento' => $p['vencimento'],
                    'status' => 'pendente',
                ]);
            }

            return $acordo;
        });
    }

    /**
     * Registra o aceite eletrônico formal da família no acordo.
     */
    public function confirmarAceite(AcordoInadimplencia $acordo, string $ip, ?string $userAgent = null): void
    {
        $acordo->update([
            'status' => StatusAcordoInadimplencia::Ativo,
            'aceito_em' => now(),
            'ip_aceite' => $ip,
            'user_agent_aceite' => $userAgent,
            // Congela o texto que a família acabou de ler: a partir daqui o termo aceito não é mais regerado.
            'termo_confissao_texto' => $this->gerarMinutaConfissaoDivida($acordo),
        ]);
    }

    /**
     * Termo a exibir: enquanto não há aceite, é sempre gerado na hora a partir dos dados atuais do acordo (assim
     * acordos criados com um texto antigo ou defeituoso saem corretos e edições nos valores são refletidas);
     * depois do aceite, vale o texto gravado, que não pode mais mudar.
     */
    public function termoParaExibicao(AcordoInadimplencia $acordo): string
    {
        if ($acordo->aceito_em === null) {
            return $this->gerarMinutaConfissaoDivida($acordo);
        }

        return (string) ($acordo->termo_confissao_texto ?: $this->gerarMinutaConfissaoDivida($acordo));
    }

    /**
     * Registra pagamento de uma parcela e verifica se todo o acordo foi quitado.
     */
    public function registrarPagamentoParcela(AcordoParcela $parcela, float $valorPago, string $forma = 'pix'): void
    {
        $parcela->update([
            'status' => 'pago',
            'data_pagamento' => now(),
            'valor_pago' => $valorPago,
            'forma_pagamento' => $forma,
        ]);

        $acordoId = $parcela->acordo_inadimplencia_id;
        $acordo = AcordoInadimplencia::find($acordoId);

        if ($acordo) {
            // Se todas as parcelas foram quitadas, o acordo foi cumprido integralmente
            $temPendencias = AcordoParcela::where('acordo_inadimplencia_id', $acordoId)
                ->where('status', '!=', 'pago')
                ->exists();

            if (! $temPendencias) {
                $acordo->update([
                    'status' => StatusAcordoInadimplencia::Cumprido,
                ]);
            }
        }
    }

    /**
     * Gera texto jurídico padrão do Termo de Confissão e Transação de Dívida (Art. 784, III do CPC).
     */
    public function gerarMinutaConfissaoDivida(AcordoInadimplencia $acordo): string
    {
        $acordo->loadMissing(['matricula.pessoa', 'responsavelPessoa']);

        $alunoNome = $acordo->matricula?->pessoa?->nome ?? 'Aluno';
        $responsavel = $acordo->responsavelPessoa;
        $respNome = $responsavel?->nome ?? 'Responsável';
        $respCpf = $responsavel?->cpf ?? 'Não informado';

        $totalFmt = number_format((float) $acordo->valor_total_acordo, 2, ',', '.');
        $origFmt = number_format((float) $acordo->valor_original_total, 2, ',', '.');
        $descFmt = number_format((float) $acordo->valor_desconto, 2, ',', '.');
        // Chamadas de função não são interpoladas em heredoc: formatadas aqui, antes do texto.
        $parcelaFmt = number_format((float) $acordo->valor_parcela, 2, ',', '.');
        $entradaFmt = number_format((float) $acordo->valor_entrada, 2, ',', '.');

        return <<<TEXT
INSTRUMENTO PARTICULAR DE CONFISSÃO, PARCELAMENTO E TRANSAÇÃO DE DÍVIDA ESCOLAR
(Nos termos do Art. 784, inciso III, e Art. 840 do Código de Processo Civil)

CREDORA: Instituição de Ensino Torre360, devidamente qualificada nos cadastros escolares.
DEVEDOR(A): {$respNome}, inscrito(a) no CPF/MF sob o nº {$respCpf}, responsável financeiro pelo(a) estudante {$alunoNome}.

CLÁUSULA PRIMEIRA - DO OBJETO E RECONHECIMENTO DO DÉBITO:
O(A) DEVEDOR(A) reconhece expressamente, de forma líquida, certa e exigível, a dívida histórica originada de contraprestações de serviços educacionais no montante original de R$ {$origFmt}.

CLÁUSULA SEGUNDA - DA TRANSAÇÃO E CONDIÇÕES DE PAGAMENTO:
Por mera liberalidade da CREDORA e com a finalidade de viabilizar a adimplência, concede-se um desconto financeiro de R$ {$descFmt}, fixando-se o valor final e consolidado do acordo em R$ {$totalFmt}, a ser quitado nas seguintes condições:
- Quantidade de parcelas: {$acordo->quantidade_parcelas} parcela(s) no valor de R$ {$parcelaFmt};
- Entrada: R$ {$entradaFmt};
- Dia de vencimento: {$acordo->dia_vencimento_parcelas} de cada mês, com primeiro vencimento em {$acordo->primeiro_vencimento?->format('d/m/Y')}.

CLÁUSULA TERCEIRA - DA CLÁUSULA RESOLUTIVA E PERDA DO DESCONTO:
O atraso superior a 15 (quinze) dias no pagamento de qualquer uma das parcelas pactuadas importará no vencimento antecipado das parcelas vincendas, com a imediata perda do desconto ora concedido, incidindo multa moratória de 2% (dois por cento) e juros de 1% (um por cento) ao mês pro rata die.

CLÁUSULA QUARTA - DO TÍTULO EXECUTIVO:
O presente instrumento constitui TÍTULO EXECUTIVO EXTRAJUDICIAL, nos termos do Artigo 784, inciso III do Código de Processo Civil Brasileiro.

Acordo registrado sob o código {$acordo->codigo} no sistema em {$acordo->created_at?->format('d/m/Y H:i')}.
TEXT;
    }

    /**
     * Gera mensagem para envio via WhatsApp com link seguro do acordo.
     */
    public function gerarMensagemWhatsapp(AcordoInadimplencia $acordo): string
    {
        $respNome = $acordo->responsavelPessoa?->nome ?? 'Família';
        $primeiroNome = explode(' ', trim($respNome))[0];
        $totalFmt = number_format((float) $acordo->valor_total_acordo, 2, ',', '.');
        $descFmt = number_format((float) $acordo->valor_desconto, 2, ',', '.');
        $url = $acordo->urlAceitePublica();

        return "Olá, {$primeiroNome}! Tudo bem? 🤝\n\n"
            ."Preparamos uma condição especial e facilitada para regularização do plano financeiro escolar no Colégio Torre360 (Acordo {$acordo->codigo}).\n\n"
            ."💰 *Valor Original com Desconto:* R$ {$totalFmt} (Economia de R$ {$descFmt})\n"
            ."📅 *Plano:* {$acordo->quantidade_parcelas}x de R$ ".number_format((float) $acordo->valor_parcela, 2, ',', '.')."\n\n"
            ."Você pode conferir todas as datas e confirmar o parcelamento diretamente pelo link seguro abaixo:\n"
            ."👉 {$url}\n\n"
            .'Estamos à disposição para ajudar no que for preciso!';
    }
}
