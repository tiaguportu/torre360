<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TipoTemplateDocumento;
use App\Models\CancelamentoMatricula;
use App\Models\Fatura;
use App\Models\TemplateDocumento;
use App\Models\User;
use App\Services\Documentos\MacrosDocumentoService;
use App\Services\Documentos\SignatariosSugeridosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * PDF do distrato de uma matrícula, para imprimir e assinar. Existe só depois da aprovação (é quando as decisões e os
 * valores ficam definidos) e abre para a equipe com `View:CancelamentoMatricula` e para a família do aluno. Depois de
 * concluído continua igual: lê as faturas pelas decisões gravadas, mesmo as já canceladas.
 *
 * O texto vem do modelo de documento "Distrato de Matrícula" quando existe um ativo (editável em Modelos de Documentos);
 * sem ele, vale o texto fixo da tela `pdfs.distrato-matricula`.
 */
class DistratoMatriculaPdfController extends Controller
{
    public function show(CancelamentoMatricula $cancelamento): Response
    {
        /** @var User $user */
        $user = auth()->user();

        $cancelamento->load(['matricula.pessoa', 'matricula.turma.serie.curso.unidade.instituicaoEnsino', 'matricula.turma.periodoLetivo', 'matricula.contrato.responsaveisFinanceiros.pessoa']);

        abort_unless($cancelamento->isAccessibleBy($user), 403);
        abort_unless($cancelamento->temDistrato(), 404, 'O distrato só existe depois que a escola aprova o pedido.');

        $matricula = $cancelamento->matricula;
        $unidade = $matricula->turma?->serie?->curso?->unidade;
        $escola = $unidade?->instituicaoEnsino?->nome ?? $unidade?->nome ?? config('app.name');

        $modelo = TemplateDocumento::query()
            ->where('tipo', TipoTemplateDocumento::DistratoMatricula->value)
            ->where('is_ativo', true)
            ->orderByDesc('id')
            ->first();

        if ($modelo) {
            return $this->pdfDoModelo($cancelamento, $modelo, $escola);
        }

        $decisoes = $cancelamento->decisoes ?? [];

        $faturas = $decisoes === []
            ? collect()
            : Fatura::query()
                ->whereIn('id', array_keys($decisoes))
                ->with(['itens', 'transacoes'])
                ->orderBy('vencimento')
                ->get();

        return Pdf::loadView('pdfs.distrato-matricula', [
            'cancelamento' => $cancelamento,
            'matricula' => $matricula,
            'contrato' => $matricula->contrato,
            'escola' => $escola,
            'faturas' => $faturas,
            'decisoes' => $decisoes,
            'emissao' => today(),
            'prazoMulta' => today()->addDays((int) config('cancelamento.multa_vencimento_dias', 10)),
        ])
            ->setPaper('a4')
            ->stream("distrato-matricula-{$cancelamento->id}.pdf");
    }

    private function pdfDoModelo(CancelamentoMatricula $cancelamento, TemplateDocumento $modelo, string $escola): Response
    {
        $macros = app(MacrosDocumentoService::class);

        // As linhas de assinatura da versão impressa: quem costuma assinar, ou duas linhas genéricas.
        $assinantes = array_map(
            fn (array $a): array => ['nome' => $a['nome'], 'papel' => $a['papel']],
            app(SignatariosSugeridosService::class)->para($cancelamento)['assinantes'],
        );

        $assinantes = $assinantes !== [] ? $assinantes : [['nome' => 'CONTRATANTE', 'papel' => 'Contratante'], ['nome' => $escola, 'papel' => 'Contratada']];

        return Pdf::loadView('pdfs.documento_assinatura', [
            'titulo' => $modelo->nome,
            'escola' => $escola,
            'cabecalho' => filled($modelo->cabecalho) ? $macros->substituir($modelo->cabecalho, $modelo, $cancelamento, $assinantes) : null,
            'conteudo' => $macros->preencher($modelo, $cancelamento, $assinantes),
            'rodape' => filled($modelo->rodape) ? $macros->substituir($modelo->rodape, $modelo, $cancelamento, $assinantes) : null,
            'geradoEm' => now()->format('d/m/Y H:i'),
            'codigo' => 'D'.$cancelamento->id,
        ])
            ->setPaper('a4')
            ->stream("distrato-matricula-{$cancelamento->id}.pdf");
    }
}
