<?php

declare(strict_types=1);

namespace App\Filament\Resources\RelatoriosSalvos\Schemas;

use App\Contracts\FonteDeRelatorio;
use App\Enums\FormatoRelatorio;
use App\Enums\FrequenciaAgendamento;
use App\Models\User;
use App\Services\Relatorios\CatalogoDeRelatorios;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Formulário do relatório configurável (Onda 72). As colunas e os filtros mudam conforme a fonte escolhida e só mostram o que
 * a fonte declara; a lista de fontes é a que a pessoa tem permissão de ver.
 */
class RelatorioSalvoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Relatório')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->placeholder('Ex.: Inadimplência acima de 30 dias')
                            ->required()
                            ->maxLength(120),
                        Select::make('fonte')
                            ->label('Dados de')
                            ->options(fn (): array => self::fontesDaPessoa())
                            ->helperText(fn (Get $get): ?string => self::fonte($get('fonte'))?->descricao() ?? 'Você vê só as fontes cujos dados já pode ver nas telas do sistema.')
                            ->native(false)
                            ->live()
                            ->required()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $fonte = self::fonte($state);

                                $set('colunas', $fonte ? array_keys(array_filter($fonte->colunas(), fn ($c): bool => $c->padrao)) : []);
                                $set('filtros', []);
                                $set('ordenar_por', null);
                            }),
                        Select::make('formato')
                            ->label('Arquivo')
                            ->options(FormatoRelatorio::class)
                            ->default(FormatoRelatorio::Csv->value)
                            ->native(false)
                            ->required()
                            ->helperText('A planilha aceita mais linhas; o PDF serve para ler e imprimir.'),
                    ]),
                Section::make('Colunas')
                    ->description('O que aparece no arquivo, na ordem da lista.')
                    ->visible(fn (Get $get): bool => self::fonte($get('fonte')) !== null)
                    ->schema([
                        CheckboxList::make('colunas')
                            ->hiddenLabel()
                            ->options(fn (Get $get): array => self::opcoesDeColunas(self::fonte($get('fonte'))))
                            ->columns(3)
                            ->live()
                            ->required()
                            ->minItems(1)
                            ->helperText('Colunas marcadas com (dado pessoal) levam nome, nascimento ou outro dado de pessoas: o arquivo só vai para quem pode ver esses dados.'),
                    ]),
                Section::make('Filtros e ordem')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::fonte($get('fonte')) !== null)
                    ->schema(fn (Get $get): array => [
                        ...self::camposDeFiltro(self::fonte($get('fonte'))),
                        Select::make('ordenar_por')
                            ->label('Ordenar por')
                            ->options(fn (Get $get): array => self::opcoesDeOrdem(self::fonte($get('fonte')), (array) $get('colunas')))
                            ->native(false)
                            ->placeholder('Ordem natural da fonte'),
                        Select::make('direcao')
                            ->label('Direção')
                            ->options(['asc' => 'Crescente', 'desc' => 'Decrescente'])
                            ->default('asc')
                            ->native(false),
                    ]),
                Section::make('Agendamento')
                    ->description('O sistema gera o arquivo sozinho e avisa quem você escolher. O e-mail leva só o link: o arquivo se baixa dentro do sistema, com login.')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::fonte($get('fonte')) !== null)
                    ->schema([
                        Toggle::make('agendado')
                            ->label('Gerar automaticamente')
                            ->live()
                            ->columnSpanFull(),
                        Select::make('frequencia')
                            ->label('Frequência')
                            ->options(FrequenciaAgendamento::class)
                            ->native(false)
                            ->live()
                            ->required(fn (Get $get): bool => (bool) $get('agendado'))
                            ->visible(fn (Get $get): bool => (bool) $get('agendado')),
                        Select::make('dia')
                            ->label(fn (Get $get): string => self::frequencia($get('frequencia')) === FrequenciaAgendamento::Semanal ? 'Dia da semana' : 'Dia do mês')
                            ->options(fn (Get $get): array => self::frequencia($get('frequencia')) === FrequenciaAgendamento::Semanal
                                ? [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo']
                                : array_combine(range(1, 28), range(1, 28)))
                            ->native(false)
                            ->helperText(fn (Get $get): ?string => self::frequencia($get('frequencia')) === FrequenciaAgendamento::Mensal ? 'Vai até o dia 28 para valer em todos os meses.' : null)
                            ->required(fn (Get $get): bool => (bool) $get('agendado') && self::usaDia($get('frequencia')))
                            ->visible(fn (Get $get): bool => (bool) $get('agendado') && self::usaDia($get('frequencia'))),
                        TimePicker::make('hora')
                            ->label('Hora')
                            ->seconds(false)
                            ->format('H:i')
                            ->helperText('O agendador roda de 5 em 5 minutos: o arquivo sai até 5 minutos depois da hora.')
                            ->required(fn (Get $get): bool => (bool) $get('agendado'))
                            ->visible(fn (Get $get): bool => (bool) $get('agendado')),
                        Select::make('destinatarios')
                            ->label('Quem recebe o aviso')
                            ->options(fn (Get $get): array => self::destinatariosPossiveis(self::fonte($get('fonte'))))
                            ->multiple()
                            ->searchable()
                            ->required(fn (Get $get): bool => (bool) $get('agendado'))
                            ->visible(fn (Get $get): bool => (bool) $get('agendado'))
                            ->helperText('Só aparecem pessoas ativas que podem ver os dados desta fonte.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function catalogo(): CatalogoDeRelatorios
    {
        return app(CatalogoDeRelatorios::class);
    }

    private static function fonte(mixed $chave): ?FonteDeRelatorio
    {
        return is_string($chave) && $chave !== '' ? self::catalogo()->da($chave) : null;
    }

    /**
     * @return array<string, string>
     */
    private static function fontesDaPessoa(): array
    {
        $usuario = auth()->user();

        if (! $usuario) {
            return [];
        }

        return array_map(fn (FonteDeRelatorio $f): string => $f->titulo(), self::catalogo()->permitidasPara($usuario));
    }

    /**
     * @return array<string, string>
     */
    private static function opcoesDeColunas(?FonteDeRelatorio $fonte): array
    {
        if (! $fonte) {
            return [];
        }

        return array_map(fn ($c): string => $c->rotulo.($c->sensivel ? ' (dado pessoal)' : ''), $fonte->colunas());
    }

    /**
     * @param  array<int, mixed>  $escolhidas
     * @return array<string, string>
     */
    private static function opcoesDeOrdem(?FonteDeRelatorio $fonte, array $escolhidas): array
    {
        if (! $fonte) {
            return [];
        }

        return array_map(fn ($c): string => $c->rotulo, array_intersect_key($fonte->colunas(), array_flip($escolhidas)));
    }

    /**
     * @return list<Component>
     */
    private static function camposDeFiltro(?FonteDeRelatorio $fonte): array
    {
        if (! $fonte) {
            return [];
        }

        $campos = [];

        foreach ($fonte->filtros() as $chave => $filtro) {
            $campos = [...$campos, ...match ($filtro->tipo) {
                'data' => [
                    DatePicker::make("filtros.{$chave}_de")->label("{$filtro->rotulo}: de")->native(false),
                    DatePicker::make("filtros.{$chave}_ate")->label("{$filtro->rotulo}: até")->native(false),
                ],
                'opcao' => [
                    Select::make("filtros.{$chave}")->label($filtro->rotulo)->options($filtro->opcoes)->native(false)->placeholder('Todos'),
                ],
                default => [
                    TextInput::make("filtros.{$chave}")->label($filtro->rotulo)->numeric()->minValue(0),
                ],
            }];
        }

        return $campos;
    }

    /**
     * O estado de um Select com enum chega como o enum ou como o texto, conforme o caminho (preenchimento, clique na tela).
     */
    private static function frequencia(mixed $valor): ?FrequenciaAgendamento
    {
        return $valor instanceof FrequenciaAgendamento ? $valor : (is_string($valor) ? FrequenciaAgendamento::tryFrom($valor) : null);
    }

    private static function usaDia(mixed $frequencia): bool
    {
        return self::frequencia($frequencia)?->usaDia() ?? false;
    }

    /**
     * Pessoas ativas que podem ver os dados da fonte (pela permissão em algum papel, ou super admin).
     *
     * @return array<int, string>
     */
    private static function destinatariosPossiveis(?FonteDeRelatorio $fonte): array
    {
        if (! $fonte) {
            return [];
        }

        try {
            return User::query()
                ->ativos()
                ->where(fn ($q) => $q->permission($fonte->permissao())->orWhereHas('roles', fn ($r) => $r->where('name', 'super_admin')))
                ->orderBy('name')
                ->limit(300)
                ->pluck('name', 'id')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
