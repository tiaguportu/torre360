<?php

namespace App\Services;

use App\Enums\StatusFatura;
use App\Models\Banco;
use App\Models\Fatura;
use App\Models\TransacaoBancaria;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Registra o recebimento de uma fatura (baixa) e atualiza o status dela de acordo com o
 * saldo restante. Extraído de `FaturasTable::darBaixaAction()` para ser reutilizado pelo
 * webhook de pagamento (`PagamentoWebhookController`) e pela conciliação bancária
 * automática (`ConciliacaoBancariaService::conciliarCreditosComFaturas()`), que fazem a
 * mesma coisa a partir de fontes diferentes (operador humano, gateway, extrato importado).
 */
class BaixaFaturaService
{
    /**
     * @param  array{banco_id?: int|null, valor: float, data_transacao: string, descricao?: string|null, conciliado?: bool, gateway?: string|null, gateway_id?: string|null, external_id?: string|null}  $dados
     */
    public function darBaixa(Fatura $fatura, array $dados): TransacaoBancaria
    {
        return DB::transaction(function () use ($fatura, $dados): TransacaoBancaria {
            $transacao = TransacaoBancaria::create([
                'banco_id' => $dados['banco_id'] ?? $this->bancoIdPadrao(),
                'fatura_id' => $fatura->id,
                'tipo' => 'entrada',
                'valor' => $dados['valor'],
                'data_transacao' => $dados['data_transacao'],
                'descricao' => $dados['descricao'] ?? "Baixa — Fatura #{$fatura->id}",
                'conciliado' => $dados['conciliado'] ?? true,
                'external_id' => $dados['external_id'] ?? null,
            ]);

            $this->atualizarStatusFatura($fatura);

            return $transacao;
        });
    }

    /**
     * Recalcula o saldo devedor da fatura e ajusta o status: Pago (saldo zerado), Parcial
     * (saldo positivo, mas já houve algum recebimento) ou mantém o status atual nos demais
     * casos (ex.: Cancelado nunca é reaberto por uma baixa).
     */
    public function atualizarStatusFatura(Fatura $fatura): void
    {
        $fatura->refresh();

        if ($fatura->status === StatusFatura::Cancelado) {
            return;
        }

        $novoSaldo = $fatura->valor_restante;

        if ($novoSaldo <= 0) {
            $fatura->update(['status' => StatusFatura::Pago]);
        } elseif (in_array($fatura->status, [StatusFatura::Pendente, StatusFatura::Atrasado], true)) {
            $fatura->update(['status' => StatusFatura::Parcial]);
        }
    }

    /**
     * Banco de destino usado quando a baixa não veio de um operador escolhendo o banco na
     * tela (webhook de gateway, conciliação automática): configurável via
     * `PAGAMENTOS_BANCO_ID_PADRAO` no `.env`, com fallback para o primeiro banco ativo
     * cadastrado — evita um erro de banco de dados obscuro quando nada foi configurado.
     */
    private function bancoIdPadrao(): int
    {
        $bancoIdConfigurado = config('pagamentos.banco_id_padrao');

        if ($bancoIdConfigurado && Banco::whereKey($bancoIdConfigurado)->exists()) {
            return (int) $bancoIdConfigurado;
        }

        $bancoAtivo = Banco::where('is_active', true)->orderBy('id')->first();

        if (! $bancoAtivo) {
            throw new RuntimeException('Nenhum banco informado e nenhum banco ativo cadastrado para usar como padrão. Configure PAGAMENTOS_BANCO_ID_PADRAO ou cadastre um banco ativo.');
        }

        return $bancoAtivo->id;
    }
}
