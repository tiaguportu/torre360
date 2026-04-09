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

            // Verifica se já existe (evita duplicidade pelo FITID/external_id)
            if (TransacaoBancaria::where('external_id', $fitid)->exists()) {
                continue;
            }

            // Busca fornecedor se for débito e tiver CNPJ no memo
            $fornecedorId = null;
            if ($tipo === 'DEBIT' && $memo) {
                // Regex para CNPJ: XX.XXX.XXX/XXXX-XX
                if (preg_match('/(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})/', $memo, $cnpjMatches)) {
                    $cnpj = $cnpjMatches[1];

                    // Tenta extrair o nome do fornecedor (o que vem antes do CNPJ)
                    // No padrão do exemplo: "... - NOME - CNPJ"
                    $nomeFornecedor = $memo;
                    if (preg_match('/-\s*(.*?)\s*-\s*'.preg_quote($cnpj, '/').'/', $memo, $nameMatches)) {
                        $nomeFornecedor = trim($nameMatches[1]);
                    }

                    $fornecedor = Fornecedor::firstOrCreate(
                        ['cnpj' => $cnpj],
                        ['razao_social' => $nomeFornecedor]
                    );

                    $fornecedorId = $fornecedor->id;
                }
            }

            TransacaoBancaria::create([
                'banco_id' => $bancoId,
                'fornecedor_id' => $fornecedorId,
                'tipo' => (str_contains(strtoupper($tipo), 'CREDIT') || $valor > 0) ? 'entrada' : 'saida',
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
