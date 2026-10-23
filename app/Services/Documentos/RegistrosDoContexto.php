<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\ContextoDocumento;
use App\Models\CancelamentoMatricula;
use App\Models\Funcionario;
use App\Models\Matricula;

/**
 * Busca e rótulo dos registros que um modelo de documento pode usar (Onda 69), para os campos de escolha da prévia do
 * modelo e da criação de documentos para assinatura.
 */
final class RegistrosDoContexto
{
    /**
     * @return array<int, string>
     */
    public static function buscar(ContextoDocumento $contexto, string $busca, int $limite = 30): array
    {
        $busca = trim($busca);

        return match ($contexto) {
            ContextoDocumento::Matricula => Matricula::query()
                ->when($busca !== '', fn ($consulta) => $consulta->whereHas('pessoa', fn ($pessoa) => $pessoa->where('nome', 'like', "%{$busca}%")))
                ->with(['pessoa', 'turma'])
                ->latest('id')
                ->limit($limite)
                ->get()
                ->mapWithKeys(fn (Matricula $matricula): array => [$matricula->id => self::rotuloMatricula($matricula)])
                ->all(),
            ContextoDocumento::CancelamentoMatricula => CancelamentoMatricula::query()
                ->when($busca !== '', fn ($consulta) => $consulta->whereHas('matricula.pessoa', fn ($pessoa) => $pessoa->where('nome', 'like', "%{$busca}%")))
                ->with('matricula.pessoa')
                ->latest('id')
                ->limit($limite)
                ->get()
                ->mapWithKeys(fn (CancelamentoMatricula $cancelamento): array => [$cancelamento->id => self::rotuloCancelamento($cancelamento)])
                ->all(),
            ContextoDocumento::Funcionario => Funcionario::query()
                ->when($busca !== '', fn ($consulta) => $consulta->whereHas('pessoa', fn ($pessoa) => $pessoa->where('nome', 'like', "%{$busca}%")))
                ->with('pessoa')
                ->limit($limite)
                ->get()
                ->mapWithKeys(fn (Funcionario $funcionario): array => [$funcionario->id => self::rotuloFuncionario($funcionario)])
                ->all(),
        };
    }

    public static function rotulo(ContextoDocumento $contexto, mixed $valor): ?string
    {
        if (! $valor) {
            return null;
        }

        $id = (int) $valor;

        return match ($contexto) {
            ContextoDocumento::Matricula => ($matricula = Matricula::query()->with(['pessoa', 'turma'])->find($id)) ? self::rotuloMatricula($matricula) : null,
            ContextoDocumento::CancelamentoMatricula => ($cancelamento = CancelamentoMatricula::query()->with('matricula.pessoa')->find($id)) ? self::rotuloCancelamento($cancelamento) : null,
            ContextoDocumento::Funcionario => ($funcionario = Funcionario::query()->with('pessoa')->find($id)) ? self::rotuloFuncionario($funcionario) : null,
        };
    }

    private static function rotuloMatricula(Matricula $matricula): string
    {
        return ($matricula->pessoa?->nome ?? 'Aluno').' — '.($matricula->turma?->nome ?? 'sem turma');
    }

    private static function rotuloCancelamento(CancelamentoMatricula $cancelamento): string
    {
        return ($cancelamento->matricula?->pessoa?->nome ?? 'Aluno').' — saída em '.$cancelamento->data_efetiva->format('d/m/Y');
    }

    private static function rotuloFuncionario(Funcionario $funcionario): string
    {
        return trim(($funcionario->pessoa?->nome ?? 'Funcionário').' — '.$funcionario->cargo, ' —');
    }
}
