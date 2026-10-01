<?php

namespace App\Services;

use App\Models\Fatura;
use App\Models\Fornecedor;
use App\Models\TransacaoBancaria;
use Carbon\Carbon;

class ConciliacaoBancariaService
{
    public function __construct(private BaixaFaturaService $baixaFaturaService) {}

    public function processarOfx(string $content, int $bancoId): int
    {
        // Parser simplificado de OFX (baseado em regex para extrair STMTTRN)
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/s', $content, $matches);

        $count = 0;
        foreach ($matches[1] as $trn) {
            $tipo = $this->extractTag($trn, 'TRNTYPE'); // DEBIT ou CREDIT
            $data = $this->extractTag($trn, 'DTPOSTED'); // YYYYMMDD...
            $valor = (float) $this->extractTag($trn, 'TRNAMT');
            $fitid = $this->extractTag($trn, 'FITID');
            $memo = $this->extractTag($trn, 'MEMO') ?: $this->extractTag($trn, 'NAME');

            // Formata data
            $dataTransacao = Carbon::createFromFormat('Ymd', substr($data, 0, 8));

            // Busca fornecedor se for débito e tiver CNPJ no memo
            $fornecedorId = null;
            $tipoUpper = strtoupper(trim($tipo ?? ''));

            // Considera débito se o tipo for DEBIT ou se o valor for negativo
            if (($tipoUpper === 'DEBIT' || $valor < 0) && $memo) {
                // Regex para CNPJ brasileira: com ou sem máscara
                if (preg_match('/(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})|(\d{14})/', $memo, $cnpjMatches)) {
                    $cnpj = ! empty($cnpjMatches[1]) ? $cnpjMatches[1] : $cnpjMatches[0];

                    // Tenta extrair o nome do fornecedor (o que vem antes do CNPJ)
                    // No padrão comum observado: "... - NOME - CNPJ"
                    $nomeFornecedor = null;
                    if (preg_match('/-\s*(.*?)\s*-\s*'.preg_quote($cnpj, '/').'/', $memo, $nameMatches)) {
                        $nomeFornecedor = trim($nameMatches[1]);
                    }

                    // Se falhou na regex específica, tenta pegar a parte entre hífens
                    if (! $nomeFornecedor) {
                        $parts = explode('-', $memo);
                        if (count($parts) >= 2) {
                            $nomeFornecedor = trim($parts[count($parts) - 2]);
                        }
                    }

                    $fornecedor = Fornecedor::firstOrCreate(
                        ['cnpj' => $cnpj],
                        ['razao_social' => $nomeFornecedor ?: $memo]
                    );

                    $fornecedorId = $fornecedor->id;
                }
            }

            // Verifica se já existe (evita duplicidade pelo FITID/external_id)
            $transacaoExistente = TransacaoBancaria::where('external_id', $fitid)->first();
            if ($transacaoExistente) {
                // Se a transação já existe mas não tem fornecedor, e encontramos um agora, atualiza
                if (! $transacaoExistente->fornecedor_id && $fornecedorId) {
                    $transacaoExistente->update(['fornecedor_id' => $fornecedorId]);
                }

                continue;
            }

            TransacaoBancaria::create([
                'banco_id' => $bancoId,
                'fornecedor_id' => $fornecedorId,
                'tipo' => (str_contains($tipoUpper, 'CREDIT') || $valor > 0) ? 'entrada' : 'saida',
                'valor' => abs($valor),
                'data_transacao' => $dataTransacao,
                'descricao' => $memo,
                'external_id' => $fitid,
                'conciliado' => false,
            ]);

            $count++;
        }

        $this->conciliarCreditosComFaturas($bancoId);

        return $count;
    }

    public function processarCsv(string $path, int $bancoId): int
    {
        $handle = fopen($path, 'r');
        $count = 0;

        // Assume cabeçalho na primeira linha
        $header = fgetcsv($handle, 0, ';');
        if (! $header) {
            $header = fgetcsv($handle, 0, ',');
        } // tenta vírgula

        while (($data = fgetcsv($handle, 0, ';')) !== false || ($data = fgetcsv($handle, 0, ',')) !== false) {
            if (count($data) < 3) {
                continue;
            }

            // Lógica genérica de colunas (data, descrição, valor)
            // Idealmente o usuário mapearia as colunas, mas vamos assumir um padrão
            // Data (0), Memo (1), Valor (2)
            try {
                $dataTransacao = Carbon::parse($data[0]);
                $memo = $data[1];
                $valor = (float) str_replace(',', '.', $data[2]);

                TransacaoBancaria::create([
                    'banco_id' => $bancoId,
                    'tipo' => $valor > 0 ? 'entrada' : 'saida',
                    'valor' => abs($valor),
                    'data_transacao' => $dataTransacao,
                    'descricao' => $memo,
                    'conciliado' => false,
                ]);
                $count++;
            } catch (\Exception $e) {
                continue;
            }
        }
        fclose($handle);

        $this->conciliarCreditosComFaturas($bancoId);

        return $count;
    }

    /**
     * Tenta casar créditos importados (entradas) ainda não vinculados a uma fatura, por
     * identificador explícito na descrição ou por valor + janela de data em torno do
     * vencimento. Só concilia automaticamente quando há exatamente UMA fatura em aberto
     * candidata — valores ambíguos (duas faturas do mesmo valor no período) ficam para
     * conciliação manual, evitando vincular ao título errado.
     *
     * Roda automaticamente ao final de cada importação de extrato, e pode ser chamada de
     * novo manualmente para reprocessar transações que ficaram pendentes (ex.: a fatura
     * só foi gerada depois do crédito ter sido importado).
     *
     * @return int quantidade de transações conciliadas nesta chamada
     */
    public function conciliarCreditosComFaturas(?int $bancoId = null): int
    {
        $pendentes = TransacaoBancaria::query()
            ->where('tipo', 'entrada')
            ->where('conciliado', false)
            ->whereNull('fatura_id')
            ->when($bancoId, fn ($q) => $q->where('banco_id', $bancoId))
            ->get();

        $conciliadas = 0;

        foreach ($pendentes as $transacao) {
            $fatura = $this->encontrarFaturaCorrespondente($transacao);

            if (! $fatura) {
                continue;
            }

            $transacao->update(['fatura_id' => $fatura->id, 'conciliado' => true]);
            $this->baixaFaturaService->atualizarStatusFatura($fatura);
            $conciliadas++;
        }

        return $conciliadas;
    }

    private function encontrarFaturaCorrespondente(TransacaoBancaria $transacao): ?Fatura
    {
        $statusAbertos = ['pendente', 'atrasado', 'parcial'];

        // 1. Identificador explícito na descrição do extrato (ex.: "Pagamento Fatura #123")
        if ($transacao->descricao && preg_match('/fatura\s*#?\s*(\d+)/i', $transacao->descricao, $matches)) {
            $fatura = Fatura::whereIn('status', $statusAbertos)->find((int) $matches[1]);

            if ($fatura && abs($fatura->valor_restante - $transacao->valor) < 0.01) {
                return $fatura;
            }
        }

        // 2. Valor + janela de data em torno do vencimento (pagamento pode chegar antes
        // ou com atraso em relação à data de vencimento original).
        $dataTransacao = Carbon::parse($transacao->data_transacao);

        $candidatas = Fatura::whereIn('status', $statusAbertos)
            ->whereBetween('vencimento', [
                $dataTransacao->copy()->subDays(45)->toDateString(),
                $dataTransacao->copy()->addDays(10)->toDateString(),
            ])
            ->get()
            ->filter(fn (Fatura $f) => abs($f->valor_restante - $transacao->valor) < 0.01);

        return $candidatas->count() === 1 ? $candidatas->first() : null;
    }

    private function extractTag(string $content, string $tag): ?string
    {
        preg_match("/<{$tag}>(.*?)(?:<|\r|\n)/", $content, $match);

        return isset($match[1]) ? trim($match[1]) : null;
    }
}
