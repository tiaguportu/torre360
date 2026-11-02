<?php

declare(strict_types=1);

namespace App\Support\Requerimentos;

use App\Models\AtendimentoChamado;
use App\Models\User;
use App\Services\AtestadoAlunoService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Atestado enviado pela família pelo Portal (Onda 80). A secretaria confere o comprovante e, ao aplicar, ele vira um
 * `AtestadoAluno` (que justifica as faltas do período). Abonar é decisão da escola, e por isso é uma opção na hora de aplicar.
 */
final class EnvioDeAtestado implements AplicaRequerimento
{
    public function __construct(private readonly AtestadoAlunoService $atestados) {}

    public function diferencas(AtendimentoChamado $chamado): array
    {
        $dados = $chamado->dados ?? [];

        return [
            ['rotulo' => 'Aluno', 'antes' => null, 'depois' => $chamado->matricula?->pessoa?->nome],
            ['rotulo' => 'Período do atestado', 'antes' => null, 'depois' => $this->periodo($dados)],
        ];
    }

    public function opcoes(): array
    {
        return ['abona_faltas' => 'Abonar as faltas do período (contam como presença)'];
    }

    public function aplicar(AtendimentoChamado $chamado, User $por, array $opcoes = []): string
    {
        $matricula = $chamado->matricula;
        $dados = $chamado->dados ?? [];

        if (! $matricula) {
            throw new \DomainException('O pedido não indica o aluno.');
        }

        $caminho = $dados['comprovante'] ?? null;

        if (! $caminho || ! Storage::disk('local')->exists($caminho)) {
            throw new \DomainException('O comprovante enviado não foi encontrado. Peça o reenvio à família.');
        }

        $arquivo = new UploadedFile(Storage::disk('local')->path($caminho), basename($caminho), null, null, true);

        $atestado = $this->atestados->registrar(
            $matricula,
            Carbon::parse($dados['inicio']),
            Carbon::parse($dados['fim']),
            $arquivo,
            filled($dados['observacao'] ?? null) ? (string) $dados['observacao'] : null,
            $por,
            (bool) ($opcoes['abona_faltas'] ?? false),
        );

        return 'Registramos o atestado de '.$atestado->periodo().'.';
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function periodo(array $dados): string
    {
        try {
            return Carbon::parse($dados['inicio'])->format('d/m/Y').' a '.Carbon::parse($dados['fim'])->format('d/m/Y');
        } catch (\Throwable) {
            return 'período não informado';
        }
    }
}
