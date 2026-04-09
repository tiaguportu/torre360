<?php

namespace App\Services;

use App\Models\Fornecedor;
use App\Models\TransacaoBancaria;
use Carbon\Carbon;

class ConciliacaoBancariaService
{
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

        return $count;
    }

    private function extractTag(string $content, string $tag): ?string
    {
        preg_match("/<{$tag}>(.*?)(?:<|\r|\n)/", $content, $match);

        return isset($match[1]) ? trim($match[1]) : null;
    }
}
