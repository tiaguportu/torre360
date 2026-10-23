<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\ContextoDocumento;
use App\Models\CancelamentoMatricula;
use App\Models\Funcionario;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Quem costuma assinar cada tipo de documento (Onda 69): a família (responsáveis financeiros; sem eles, os pais) e o
 * representante legal da unidade nos documentos de aluno; o funcionário e o representante legal nos de RH. É só a
 * sugestão da tela: quem cria o documento confere e ajusta a lista.
 */
class SignatariosSugeridosService
{
    public const PAPEL_FAMILIA = 'Responsável financeiro';

    public const PAPEL_ESCOLA = 'Escola';

    public const PAPEL_FUNCIONARIO = 'Funcionário(a)';

    public function __construct(private readonly MacrosDocumentoService $macros) {}

    /**
     * @return array{assinantes: list<array{nome: string, email: string, papel: string}>, sem_email: list<string>}
     */
    public function para(Model $documentavel): array
    {
        $assinantes = collect();
        $semEmail = [];

        $adicionar = function (?Pessoa $pessoa, string $papel) use (&$assinantes, &$semEmail): void {
            if (! $pessoa) {
                return;
            }

            $emails = $this->emailsDa($pessoa);

            if ($emails === []) {
                $semEmail[] = $pessoa->nome;

                return;
            }

            foreach ($emails as $email) {
                $assinantes->push(['nome' => $pessoa->nome, 'email' => $email, 'papel' => $papel]);
            }
        };

        foreach (ContextoDocumento::cases() as $contexto) {
            $registro = $this->macros->registroDoContexto($documentavel, $contexto);

            if (! $registro) {
                continue;
            }

            match (true) {
                $registro instanceof Matricula => $this->familiaDaMatricula($registro, $adicionar),
                $registro instanceof CancelamentoMatricula => $this->familiaDaMatricula(Matricula::query()->findOrFail($registro->matricula_id), $adicionar),
                $registro instanceof Funcionario => $this->funcionario($registro, $adicionar),
                default => null,
            };

            break;
        }

        return [
            'assinantes' => $this->semRepetidos($assinantes),
            'sem_email' => array_values(array_unique($semEmail)),
        ];
    }

    private function familiaDaMatricula(Matricula $matricula, callable $adicionar): void
    {
        $matricula->loadMissing(['pessoa.responsaveis.users', 'contrato.responsaveisFinanceiros.pessoa.users', 'turma.serie.curso.unidade.representantesLegais.users']);

        $financeiros = $matricula->contrato?->responsaveisFinanceiros->map(fn ($r) => $r->pessoa)->filter() ?? collect();

        if ($financeiros->isNotEmpty()) {
            $financeiros->each(fn (Pessoa $pessoa) => $adicionar($pessoa, self::PAPEL_FAMILIA));
        } else {
            // Sem responsável financeiro cadastrado: os responsáveis do aluno.
            $matricula->pessoa?->responsaveis->each(fn (Pessoa $pessoa) => $adicionar($pessoa, 'Responsável'));
        }

        $this->representantes($matricula->turma?->serie?->curso?->unidade, $adicionar);
    }

    private function funcionario(Funcionario $funcionario, callable $adicionar): void
    {
        $funcionario->loadMissing(['pessoa.users', 'unidade.representantesLegais.users']);

        $adicionar($funcionario->pessoa, self::PAPEL_FUNCIONARIO);
        $this->representantes($funcionario->unidade, $adicionar);
    }

    private function representantes(?Unidade $unidade, callable $adicionar): void
    {
        $unidade?->representantesLegais->each(fn (Pessoa $pessoa) => $adicionar($pessoa, self::PAPEL_ESCOLA));
    }

    /**
     * E-mails da pessoa: o da ficha e o dos usuários ligados a ela (o primeiro que existir basta para assinar).
     *
     * @return list<string>
     */
    private function emailsDa(Pessoa $pessoa): array
    {
        $pessoa->loadMissing('users');

        $emails = collect([$pessoa->email])
            ->merge($pessoa->users->pluck('email'))
            ->map(fn ($email): string => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();

        // Um assinante por pessoa: o e-mail da ficha, ou o do primeiro usuário.
        return $emails->take(1)->all();
    }

    /**
     * @param  Collection<int, array{nome: string, email: string, papel: string}>  $assinantes
     * @return list<array{nome: string, email: string, papel: string}>
     */
    private function semRepetidos(Collection $assinantes): array
    {
        return $assinantes->unique('email')->values()->all();
    }
}
