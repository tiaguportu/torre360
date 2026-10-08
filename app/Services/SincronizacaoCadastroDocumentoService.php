<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Pessoa;
use App\Models\TipoContatoInteressado;
use App\Rules\Cpf;
use Illuminate\Support\Carbon;

/**
 * Preenche o cadastro (responsável ou aluno) com os dados que a IA extraiu de um documento enviado pela família.
 *
 * A IA devolve tudo como texto livre — datas em DD/MM/AAAA (conforme o prompt), CPF com ou sem máscara — e o
 * resultado pode ser ilegível ou errado. Por isso nada é gravado sem validação:
 *
 *  - datas são lidas estritamente (DD/MM/AAAA, DD-MM-AAAA, DD.MM.AAAA ou ISO), nunca por `Carbon::parse()`, que
 *    interpreta "05/03/2015" como 3 de maio e lança exceção em "15/03/2015";
 *  - data inexistente, futura ou anterior a 1900 é descartada;
 *  - CPF precisa ter dígitos verificadores válidos e não pode já pertencer a outra pessoa (a coluna é única);
 *  - só campos ainda vazios no cadastro são preenchidos; o que foi descartado volta com o motivo.
 */
class SincronizacaoCadastroDocumentoService
{
    /**
     * @return array{sem_dados: bool, alterados: list<string>, ignorados: list<string>}
     */
    public function sincronizar(DocumentoInserido $documento, ?int $usuarioId = null): array
    {
        $extraidos = $documento->dados_ia['dados_extraidos'] ?? [];

        if (! is_array($extraidos) || $extraidos === []) {
            return ['sem_dados' => true, 'alterados' => [], 'ignorados' => []];
        }

        $alterados = [];
        $ignorados = [];

        $dependente = $documento->interessado_dependente_id ? $documento->dependente : null;

        if ($dependente) {
            if (filled($extraidos['data_nascimento'] ?? null) && empty($dependente->data_nascimento)) {
                $data = self::normalizarData($extraidos['data_nascimento']);

                if ($data === null) {
                    $ignorados[] = $this->motivoDataInvalida('Data de nascimento do aluno', $extraidos['data_nascimento']);
                } else {
                    $dependente->update(['data_nascimento' => $data]);
                    $alterados[] = 'Data de nascimento do aluno ('.Carbon::parse($data)->format('d/m/Y').')';
                }
            }
        } elseif ($pessoa = $documento->interessado?->pessoa) {
            $this->sincronizarPessoa($pessoa, $extraidos, $alterados, $ignorados);
        }

        if ($alterados !== [] && $documento->interessado_id) {
            $this->registrarNaLinhaDoTempo($documento, $alterados, $usuarioId);
        }

        return ['sem_dados' => false, 'alterados' => $alterados, 'ignorados' => $ignorados];
    }

    /**
     * Converte uma data textual extraída pela IA para `Y-m-d`, ou null se não for uma data de nascimento plausível.
     */
    public static function normalizarData(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $texto, $m)) {
            [$dia, $mes, $ano] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{4})-(\d{2})-(\d{2})(?:[T\s].*)?$#', $texto, $m)) {
            [$ano, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        if ($ano < 1900 || ! checkdate($mes, $dia, $ano)) {
            return null;
        }

        $data = Carbon::create($ano, $mes, $dia)->startOfDay();

        return $data->isFuture() ? null : $data->toDateString();
    }

    /**
     * @param  array<string, mixed>  $extraidos
     * @param  list<string>  $alterados
     * @param  list<string>  $ignorados
     */
    private function sincronizarPessoa(Pessoa $pessoa, array $extraidos, array &$alterados, array &$ignorados): void
    {
        $atualizacoes = [];

        if (filled($extraidos['cpf'] ?? null) && empty($pessoa->cpf)) {
            $cpf = preg_replace('/\D/', '', (string) $extraidos['cpf']);

            if (! Cpf::valido($cpf)) {
                $ignorados[] = "CPF «{$extraidos['cpf']}» não é válido (dígitos verificadores); não foi aplicado.";
            } elseif (Pessoa::query()->where('cpf', $cpf)->where('id', '!=', $pessoa->id)->exists()) {
                $ignorados[] = "CPF «{$extraidos['cpf']}» já pertence a outra pessoa cadastrada; não foi aplicado.";
            } else {
                $atualizacoes['cpf'] = $cpf;
                $alterados[] = 'CPF ('.$extraidos['cpf'].')';
            }
        }

        if (filled($extraidos['rg'] ?? null) && empty($pessoa->identidade)) {
            $rg = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $extraidos['rg']));

            if ($rg !== '' && mb_strlen($rg) <= 30) {
                $atualizacoes['identidade'] = $rg;
                $alterados[] = 'RG ('.$rg.')';
            } else {
                $ignorados[] = "RG «{$extraidos['rg']}» está em formato inesperado; não foi aplicado.";
            }
        }

        if (filled($extraidos['data_nascimento'] ?? null) && empty($pessoa->data_nascimento)) {
            $data = self::normalizarData($extraidos['data_nascimento']);

            if ($data === null) {
                $ignorados[] = $this->motivoDataInvalida('Data de nascimento', $extraidos['data_nascimento']);
            } else {
                $atualizacoes['data_nascimento'] = $data;
                $alterados[] = 'Data de nascimento ('.Carbon::parse($data)->format('d/m/Y').')';
            }
        }

        if ($atualizacoes !== []) {
            $pessoa->update($atualizacoes);
        }
    }

    private function motivoDataInvalida(string $campo, mixed $valor): string
    {
        return "{$campo}: «{$valor}» não é uma data válida (esperado DD/MM/AAAA, não futura); não foi aplicada.";
    }

    /**
     * Trilha de auditoria: dado pessoal gravado a partir de leitura automática precisa aparecer na linha do tempo.
     *
     * @param  list<string>  $alterados
     */
    private function registrarNaLinhaDoTempo(DocumentoInserido $documento, array $alterados, ?int $usuarioId): void
    {
        $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'Portal de Admissão']);
        $tipoDocumento = $documento->tipoDocumento?->nome ?? 'documento';

        HistoricoContato::create([
            'interessado_id' => $documento->interessado_id,
            'usuario_id' => $usuarioId,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => "🔄 Cadastro preenchido a partir do documento '{$tipoDocumento}' (leitura automática, confirmada pela secretaria): ".implode(', ', $alterados).'.',
            'data_contato' => now(),
            'automatico' => true,
        ]);
    }
}
