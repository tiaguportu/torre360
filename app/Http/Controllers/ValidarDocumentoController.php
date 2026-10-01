<?php

namespace App\Http\Controllers;

use App\Models\HistoricoEscolar;
use App\Models\SolicitacaoDocumento;
use Illuminate\Http\Request;

class ValidarDocumentoController extends Controller
{
    /**
     * Exibe a página de validação pública de autenticidade documental.
     */
    public function __invoke(Request $request, ?string $codigo = null)
    {
        $codigoBusca = $codigo ?: $request->input('codigo');
        $documento = null;
        $buscou = false;
        $nomeAlunoMascarado = null;

        $historico = null;

        if (! empty($codigoBusca)) {
            $buscou = true;
            $codigoLimpo = strtoupper(trim($codigoBusca));

            // Proteção contra enumeração/raspagem: busca estrita apenas pelo código de verificação alfanumérico (alta entropia)
            $documento = SolicitacaoDocumento::query()
                ->where('codigo_verificacao', $codigoLimpo)
                ->with(['matricula.pessoa', 'matricula.turma.serie.curso.unidade', 'templateDocumento'])
                ->first();

            if ($documento && $documento->matricula?->pessoa?->nome) {
                $nomeAlunoMascarado = self::mascararNome($documento->matricula->pessoa->nome);
            }

            // Se não encontrou em SolicitacaoDocumento, busca em HistoricoEscolar
            if (! $documento) {
                $historico = HistoricoEscolar::query()
                    ->where('codigo_autenticidade', $codigoLimpo)
                    ->with(['pessoa', 'curso', 'unidade'])
                    ->first();

                if ($historico && $historico->pessoa?->nome) {
                    $nomeAlunoMascarado = self::mascararNome($historico->pessoa->nome);
                }
            }
        }

        return view('documentos.validar', [
            'documento' => $documento,
            'historico' => $historico,
            'codigoBusca' => $codigoBusca,
            'buscou' => $buscou,
            'nomeAlunoMascarado' => $nomeAlunoMascarado,
        ]);
    }

    /**
     * Mascara o nome do estudante para preservação da privacidade e conformidade com a LGPD em consultas públicas.
     */
    public static function mascararNome(?string $nome): string
    {
        if (blank($nome)) {
            return '-';
        }

        $partes = preg_split('/\s+/', trim($nome));
        $mascaradas = array_map(function (string $parte) {
            $len = mb_strlen($parte);
            if ($len <= 2) {
                return $parte; // Conectivos curtos como da, de, do, e
            }

            $primeiraLetra = mb_substr($parte, 0, 1);
            $resto = str_repeat('*', min(max($len - 1, 3), 6));

            return $primeiraLetra.$resto;
        }, $partes);

        return implode(' ', $mascaradas);
    }
}
