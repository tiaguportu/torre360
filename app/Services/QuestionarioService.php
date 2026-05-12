<?php

namespace App\Services;

use App\Models\Questionario;
use App\Models\QuestionarioBloco;
use App\Models\QuestionarioPergunta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuestionarioService
{
    /**
     * Exporta a estrutura do questionário para CSV.
     */
    public function exportToCsv(Questionario $questionario): string
    {
        $handle = fopen('php://temp', 'r+');

        // Cabeçalho
        fputcsv($handle, [
            'bloco_identificador',
            'bloco_titulo',
            'bloco_descricao',
            'bloco_ordem',
            'pergunta_identificador',
            'pergunta_enunciado',
            'pergunta_tipo',
            'pergunta_opcoes',
            'pergunta_obrigatoria',
            'pergunta_ordem',
            'condicao_pergunta_identificador',
            'condicao_operador',
            'condicao_valor',
        ], ';');

        foreach ($questionario->blocos()->with('perguntas')->get() as $bloco) {
            if ($bloco->perguntas->isEmpty()) {
                fputcsv($handle, [
                    $bloco->identificador,
                    $bloco->titulo,
                    $bloco->descricao,
                    $bloco->ordem,
                    '', '', '', '', '', '', '', '', '',
                ], ';');

                continue;
            }

            foreach ($bloco->perguntas as $pergunta) {
                $condicao = $pergunta->condicao_exibicao;
                $refPerguntaIdentificador = '';

                if (! empty($condicao['pergunta_id'])) {
                    $refPergunta = QuestionarioPergunta::find($condicao['pergunta_id']);
                    $refPerguntaIdentificador = $refPergunta ? $refPergunta->identificador : '';
                }

                fputcsv($handle, [
                    $bloco->identificador,
                    $bloco->titulo,
                    $bloco->descricao,
                    $bloco->ordem,
                    $pergunta->identificador,
                    $pergunta->enunciado,
                    $pergunta->tipo,
                    json_encode($pergunta->opcoes, JSON_UNESCAPED_UNICODE),
                    $pergunta->is_obrigatoria ? '1' : '0',
                    $pergunta->ordem,
                    $refPerguntaIdentificador,
                    $condicao['operador'] ?? '',
                    $condicao['valor'] ?? '',
                ], ';');
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Importa a estrutura de um CSV para o questionário.
     */
    public function importFromCsv(Questionario $questionario, string $filePath): void
    {
        if (! file_exists($filePath)) {
            throw new \Exception('Arquivo não encontrado no caminho: '.$filePath);
        }

        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Tentar detectar o separador (ler primeira linha)
            $firstLine = fgets($handle);
            $delimiter = str_contains($firstLine, ';') ? ';' : ',';
            rewind($handle);

            $header = fgetcsv($handle, 1000, $delimiter);

            if (! $header) {
                fclose($handle);
                throw new \Exception('Arquivo CSV vazio ou inválido.');
            }

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if (count($header) === count($data)) {
                    $rows[] = array_combine($header, $data);
                }
            }
            fclose($handle);
        }

        if (empty($rows)) {
            throw new \Exception('Nenhum dado válido encontrado no arquivo CSV (verifique o cabeçalho e o delimitador).');
        }

        DB::transaction(function () use ($questionario, $rows) {
            $blocosCriados = [];
            $perguntasCriadas = [];

            // Primeiro passo: Criar/Atualizar blocos e perguntas
            foreach ($rows as $row) {
                $blocoIdentificador = $row['bloco_identificador'] ?: Str::slug($row['bloco_titulo']);

                if (! isset($blocosCriados[$blocoIdentificador])) {
                    $bloco = QuestionarioBloco::updateOrCreate(
                        [
                            'questionario_id' => $questionario->id,
                            'identificador' => $blocoIdentificador,
                        ],
                        [
                            'titulo' => $row['bloco_titulo'],
                            'descricao' => $row['bloco_descricao'],
                            'ordem' => (int) $row['bloco_ordem'],
                        ]
                    );
                    $blocosCriados[$blocoIdentificador] = $bloco;
                }

                if (! empty($row['pergunta_enunciado'])) {
                    $perguntaIdentificador = $row['pergunta_identificador'] ?: Str::slug(strip_tags($row['pergunta_enunciado']));

                    $opcoes = json_decode($row['pergunta_opcoes'], true) ?: [];

                    $pergunta = QuestionarioPergunta::updateOrCreate(
                        [
                            'questionario_bloco_id' => $blocosCriados[$blocoIdentificador]->id,
                            'identificador' => $perguntaIdentificador,
                        ],
                        [
                            'enunciado' => $row['pergunta_enunciado'],
                            'tipo' => $row['pergunta_tipo'],
                            'opcoes' => $opcoes,
                            'is_obrigatoria' => $row['pergunta_obrigatoria'] === '1',
                            'ordem' => (int) $row['pergunta_ordem'],
                        ]
                    );
                    $perguntasCriadas[$perguntaIdentificador] = $pergunta;
                }
            }

            // Segundo passo: Processar condições de exibição (agora que todas as perguntas têm ID)
            foreach ($rows as $row) {
                if (empty($row['pergunta_enunciado']) || empty($row['condicao_pergunta_identificador'])) {
                    continue;
                }

                $perguntaIdentificador = $row['pergunta_identificador'] ?: Str::slug(strip_tags($row['pergunta_enunciado']));
                $refIdentificador = $row['condicao_pergunta_identificador'];

                if (isset($perguntasCriadas[$perguntaIdentificador]) && isset($perguntasCriadas[$refIdentificador])) {
                    $perguntasCriadas[$perguntaIdentificador]->update([
                        'condicao_exibicao' => [
                            'pergunta_id' => $perguntasCriadas[$refIdentificador]->id,
                            'operador' => $row['condicao_operador'],
                            'valor' => $row['condicao_valor'],
                        ],
                    ]);
                }
            }
        });
    }
}
