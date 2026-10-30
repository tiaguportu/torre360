<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Models\Pessoa;
use App\Services\PessoaDuplicadaDetectorService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use UnitEnum;

/**
 * Cadastros duplicados (Onda 81): pares de pessoas que parecem ser a mesma. A tela só aponta: não une, não apaga e não altera cadastro.
 * Quem pode abrir o cadastro de pessoa vê a lista; quem pode editá-lo marca o par como "não é duplicata" (gêmeos, homônimos), e o par
 * não volta. A regra de detecção e o motivo de cada par estão em `PessoaDuplicadaDetectorService`.
 */
class PessoasDuplicadas extends Page implements HasTable
{
    use HasAjudaAction;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Cadastros duplicados';

    protected static ?string $navigationLabel = 'Cadastros duplicados';

    protected static ?string $slug = 'cadastros-duplicados';

    protected string $view = 'filament.pages.pessoas-duplicadas';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:Pessoa');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalcular')
                ->label('Recalcular')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    $this->servico()->esquecerCache();
                    $this->flushCachedTableRecords();
                }),
            $this->ajudaAction('Cadastros duplicados', HelpContent::make('👥', 'Cadastros duplicados', 'Pessoas que podem estar cadastradas duas vezes, para você conferir.')
                ->secao('🎯 Como a lista é feita', [
                    ['🪪', 'CPF igual', 'Dois cadastros com o mesmo CPF.'],
                    ['🎂', 'Mesmo nome e nascimento', 'Mesmo nome (sem contar acento ou maiúscula) e a mesma data de nascimento.'],
                    ['📞', 'Mesmo nome e contato', 'Mesmo nome e o mesmo e-mail ou telefone, quando as datas de nascimento e os CPFs não se contradizem.'],
                ])
                ->secao('✅ O que você pode fazer', [
                    ['🔎', 'Conferir', 'Clique no nome para abrir o cadastro. Sob o nome aparecem os vínculos de cada um (matrículas, responsáveis, acesso).'],
                    ['🙅', 'Não é duplicata', 'Gêmeos e homônimos são pessoas diferentes. Marque o par e ele não aparece de novo.'],
                ])
                ->alerta('Esta tela só aponta: ela não une nem apaga nada. Dois cadastros que só compartilham e-mail ou telefone (pai e filho, por exemplo) não entram na lista.')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->registros((int) $page, (int) $recordsPerPage))
            ->columns([
                TextColumn::make('menor')
                    ->label('Cadastro A')
                    ->state(fn (array $record): string => $record['menor']['nome'])
                    ->description(fn (array $record): string => $record['menor']['resumo'])
                    ->url(fn (array $record): ?string => $this->urlDoCadastro($record['menor']['id']))
                    ->wrap(),
                TextColumn::make('maior')
                    ->label('Cadastro B')
                    ->state(fn (array $record): string => $record['maior']['nome'])
                    ->description(fn (array $record): string => $record['maior']['resumo'])
                    ->url(fn (array $record): ?string => $this->urlDoCadastro($record['maior']['id']))
                    ->wrap(),
                TextColumn::make('motivos')
                    ->label('Por que parece duplicado')
                    ->state(fn (array $record): array => $record['motivos'])
                    ->badge(),
            ])
            ->recordActions([
                Action::make('nao_e_duplicata')
                    ->label('Não é duplicata')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('gray')
                    ->visible(fn (): bool => $this->podeMarcar())
                    ->requiresConfirmation()
                    ->modalHeading('São pessoas diferentes?')
                    ->modalDescription('Os dois cadastros deixam de ser sugeridos como duplicados. Nenhum deles é alterado.')
                    ->action(function (array $record): void {
                        abort_unless($this->podeMarcar(), 403);

                        $this->servico()->marcarComoDiferentes($record['menor']['id'], $record['maior']['id'], auth()->user());

                        // A tabela guarda os registros da requisição; sem isto a linha marcada continuaria na tela até o próximo clique.
                        $this->flushCachedTableRecords();

                        Notification::make()->title('Registrado: são pessoas diferentes.')->success()->send();
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Nenhum cadastro duplicado à vista')
            ->emptyStateDescription('Não há pares de pessoas com o mesmo CPF, ou com o mesmo nome e nascimento ou contato, ainda não conferidos.');
    }

    private function servico(): PessoaDuplicadaDetectorService
    {
        return app(PessoaDuplicadaDetectorService::class);
    }

    private function podeMarcar(): bool
    {
        return (bool) auth()->user()?->can('Update:Pessoa');
    }

    /** O cadastro de pessoa só tem tela de edição: sem `Update:Pessoa` o nome aparece, mas sem link. */
    private function urlDoCadastro(int $pessoaId): ?string
    {
        return $this->podeMarcar() ? PessoaResource::getUrl('edit', ['record' => $pessoaId]) : null;
    }

    /**
     * A página atual da lista: busca os dois cadastros de cada par de uma vez (com a contagem dos vínculos) e descreve cada um.
     *
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    private function registros(int $pagina, int $porPagina): LengthAwarePaginator
    {
        $pares = $this->servico()->pares();
        $daPagina = $pares->forPage($pagina, $porPagina)->values();

        $pessoas = Pessoa::query()
            ->whereIn('id', $daPagina->flatMap(fn (array $par): array => [$par['pessoa_menor_id'], $par['pessoa_maior_id']])->unique()->all())
            ->withCount(['matriculas', 'alunos', 'responsaveis', 'users', 'interessados'])
            ->get()
            ->keyBy('id');

        $registros = [];

        foreach ($daPagina as $par) {
            $menor = $pessoas->get($par['pessoa_menor_id']);
            $maior = $pessoas->get($par['pessoa_maior_id']);

            // O par vem de um cálculo guardado por alguns minutos: um dos cadastros pode ter sido apagado desde então.
            if ($menor === null || $maior === null) {
                continue;
            }

            $registros[$par['chave']] = [
                'menor' => $this->descrever($menor),
                'maior' => $this->descrever($maior),
                'motivos' => array_map(fn (string $motivo): string => PessoaDuplicadaDetectorService::ROTULOS[$motivo], $par['motivos']),
            ];
        }

        return new LengthAwarePaginator($registros, $pares->count(), $porPagina, $pagina);
    }

    /**
     * @return array{id: int, nome: string, resumo: string}
     */
    private function descrever(Pessoa $pessoa): array
    {
        $vinculos = array_filter([
            $pessoa->matriculas_count > 0 ? $pessoa->matriculas_count.' '.($pessoa->matriculas_count === 1 ? 'matrícula' : 'matrículas') : null,
            $pessoa->alunos_count > 0 ? 'responsável por '.$pessoa->alunos_count.' '.($pessoa->alunos_count === 1 ? 'aluno' : 'alunos') : null,
            $pessoa->responsaveis_count > 0 ? $pessoa->responsaveis_count.' '.($pessoa->responsaveis_count === 1 ? 'responsável' : 'responsáveis') : null,
            $pessoa->users_count > 0 ? 'com acesso ao sistema' : null,
            $pessoa->interessados_count > 0 ? 'no CRM' : null,
        ]);

        return [
            'id' => (int) $pessoa->getKey(),
            'nome' => (string) $pessoa->nome,
            'resumo' => implode(' · ', array_filter([
                $this->dataBr($pessoa->data_nascimento),
                $this->cpfMascarado($pessoa->cpf),
                $pessoa->email,
                $pessoa->telefone,
                $vinculos === [] ? 'sem vínculos' : implode(', ', $vinculos),
            ])),
        ];
    }

    private function dataBr(mixed $valor): ?string
    {
        $data = substr((string) $valor, 0, 10);

        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $partes) === 1 ? "{$partes[3]}/{$partes[2]}/{$partes[1]}" : null;
    }

    /** Mostra só os dígitos do meio ("***.456.789-**"): basta para comparar dois cadastros sem expor o número inteiro numa lista. */
    private function cpfMascarado(?string $cpf): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $cpf) ?? '';

        return strlen($digitos) === 11 ? '***.'.substr($digitos, 3, 3).'.'.substr($digitos, 6, 3).'-**' : null;
    }
}
