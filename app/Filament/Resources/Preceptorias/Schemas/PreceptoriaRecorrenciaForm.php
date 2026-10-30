<?php

namespace App\Filament\Resources\Preceptorias\Schemas;

use App\Exceptions\ConflitoDeHorarioException;
use App\Models\CicloPreceptoria;
use App\Models\Preceptoria;
use App\Services\PreceptoriaAgendaService;
use Carbon\Carbon;
use DomainException;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

/**
 * Campos de uma preceptoria recorrente numa janela de tempo. Usado no cadastro de preceptorias
 * (modo "recorrente") e no modal "Gerar recorrência" do calendário, com os mesmos nomes de campo.
 */
class PreceptoriaRecorrenciaForm
{
    private const DIAS_SEMANA = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        0 => 'Domingo',
    ];

    /**
     * @return array<int, Component>
     */
    public static function componentes(): array
    {
        return [
            DatePicker::make('data_inicio_range')
                ->label('Data Inicial')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->live(),

            DatePicker::make('data_fim_range')
                ->label('Data Final')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->minDate(fn (Get $get) => $get('data_inicio_range'))
                ->live(),

            Select::make('intervalo_semanas')
                ->label('Repetir')
                ->options([
                    1 => 'Toda semana',
                    2 => 'A cada 2 semanas (quinzenal)',
                    3 => 'A cada 3 semanas',
                    4 => 'A cada 4 semanas',
                ])
                ->default(1)
                ->native(false)
                ->required()
                ->live(),

            CheckboxList::make('dias_semana_range')
                ->label('Dias da Semana (opcional: sem seleção, vale todos os dias da janela)')
                ->options(fn (Get $get) => self::opcoesDiasSemana($get('ignorar_fins_de_semana') ?? true))
                ->columns(4)
                ->live()
                ->columnSpanFull(),

            Toggle::make('ignorar_fins_de_semana')
                ->label('Desconsiderar finais de semana')
                ->helperText('Desligue para permitir sábados e domingos.')
                ->default(true)
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, bool $state) {
                    if ($state) {
                        $set('dias_semana_range', array_values(array_diff(array_map('intval', $get('dias_semana_range') ?? []), [0, 6])));
                    }
                }),

            Toggle::make('ignorar_feriados')
                ->label('Desconsiderar feriados nacionais')
                ->helperText('Feriados municipais e estaduais entram como dia não letivo.')
                ->default(true)
                ->live(),

            Toggle::make('ignorar_dias_nao_letivos')
                ->label('Desconsiderar dias não letivos')
                ->helperText('Recessos e dias sem aula cadastrados no período letivo do ciclo.')
                ->default(true)
                ->live(),

            TextEntry::make('previa_recorrencia')
                ->label('Prévia da recorrência')
                ->state(fn (Get $get) => self::previa($get))
                ->html()
                ->columnSpanFull(),
        ];
    }

    /**
     * Ao escolher o ciclo (ou o modo recorrente), sugere a janela do próprio ciclo se ainda estiver vazia.
     */
    public static function sugerirJanelaDoCiclo(Get $get, Set $set): void
    {
        $ciclo = $get('ciclo_preceptoria_id') ? CicloPreceptoria::find($get('ciclo_preceptoria_id')) : null;

        if (! $ciclo) {
            return;
        }

        if ($ciclo->data_inicio && ! $get('data_inicio_range')) {
            $set('data_inicio_range', $ciclo->data_inicio->toDateString());
        }

        if ($ciclo->data_fim && ! $get('data_fim_range')) {
            $set('data_fim_range', $ciclo->data_fim->toDateString());
        }
    }

    /**
     * Estado do formulário → regras do PreceptoriaAgendaService. Os "desconsiderar" nascem ligados.
     *
     * @param  array<string, mixed>  $estado
     * @return array<string, mixed>
     */
    public static function regras(array $estado): array
    {
        $ciclo = ! empty($estado['ciclo_preceptoria_id']) ? CicloPreceptoria::find($estado['ciclo_preceptoria_id']) : null;

        return [
            'inicio' => $estado['data_inicio_range'] ?? null,
            'fim' => $estado['data_fim_range'] ?? null,
            'dias_semana' => $estado['dias_semana_range'] ?? [],
            'intervalo_semanas' => $estado['intervalo_semanas'] ?? 1,
            'ignorar_fins_de_semana' => $estado['ignorar_fins_de_semana'] ?? true,
            'ignorar_feriados' => $estado['ignorar_feriados'] ?? true,
            'ignorar_dias_nao_letivos' => $estado['ignorar_dias_nao_letivos'] ?? true,
            'periodo_letivo_id' => $ciclo?->periodo_letivo_id,
        ];
    }

    /**
     * Cria a série a partir do estado do formulário e avisa o resultado (criadas, ignoradas, puladas).
     *
     * @param  array<string, mixed>  $estado  ciclo_preceptoria_id, professor_id, matricula_id, hora_inicio, hora_fim e os campos de recorrência
     * @return array<int, Preceptoria>|null as criadas; null quando nada foi criado (já avisado ao usuário)
     */
    public static function criar(array $estado): ?array
    {
        $base = Arr::only($estado, ['ciclo_preceptoria_id', 'professor_id', 'matricula_id', 'hora_inicio', 'hora_fim']);

        try {
            $resultado = app(PreceptoriaAgendaService::class)->gerarSerie($base, self::regras($estado));
        } catch (DomainException $e) {
            Notification::make()->title('Recorrência não criada')->body($e->getMessage())->danger()->send();

            return null;
        }

        return self::avisarResultado($resultado);
    }

    /**
     * Avisa o que a geração fez: criadas, desconsideradas (fim de semana, feriado, dia não letivo) e
     * puladas por choque de horário do professor ou do aluno.
     *
     * @param  array{criadas: array<int, Preceptoria>, ignoradas: array<int, mixed>, conflitos: array<string, Preceptoria>, conflitos_aluno: array<string, Preceptoria>}  $resultado
     * @return array<int, Preceptoria>|null as criadas; null quando nada foi criado
     */
    public static function avisarResultado(array $resultado): ?array
    {
        if ($resultado['criadas'] === []) {
            $motivos = collect($resultado['conflitos'])
                ->map(fn (Preceptoria $outra) => ConflitoDeHorarioException::doProfessor($outra)->getMessage())
                ->concat(collect($resultado['conflitos_aluno'])->map(fn (Preceptoria $outra) => ConflitoDeHorarioException::doAluno($outra)->getMessage()))
                ->unique()
                ->take(3)
                ->implode(' ');

            Notification::make()
                ->title('Nenhuma preceptoria criada')
                ->body(trim('Todas as datas foram desconsideradas ou o professor (ou o aluno) já tem preceptoria no horário. '.$motivos))
                ->warning()
                ->send();

            return null;
        }

        $ignoradas = count($resultado['ignoradas']);
        $puladas = count($resultado['conflitos']);
        $puladasAluno = count(array_diff_key($resultado['conflitos_aluno'], $resultado['conflitos']));

        $detalhes = array_filter([
            $ignoradas ? "{$ignoradas} data(s) desconsiderada(s) (fim de semana, feriado ou dia não letivo)" : null,
            $puladas ? "{$puladas} data(s) puladas por já terem preceptoria do professor no horário" : null,
            $puladasAluno ? "{$puladasAluno} data(s) puladas por o aluno já ter preceptoria no horário" : null,
        ]);

        Notification::make()
            ->title(count($resultado['criadas']).' preceptoria(s) criada(s)')
            ->body($detalhes ? implode('; ', $detalhes).'.' : null)
            ->success()
            ->send();

        return $resultado['criadas'];
    }

    /**
     * @return array<int, string>
     */
    private static function opcoesDiasSemana(bool $semFimDeSemana): array
    {
        return $semFimDeSemana ? array_diff_key(self::DIAS_SEMANA, [0 => true, 6 => true]) : self::DIAS_SEMANA;
    }

    private static function previa(Get $get): HtmlString
    {
        if (! $get('data_inicio_range') || ! $get('data_fim_range')) {
            return self::texto('Informe a data inicial e a final para ver as datas que serão geradas.');
        }

        $estado = [
            'ciclo_preceptoria_id' => $get('ciclo_preceptoria_id'),
            'data_inicio_range' => $get('data_inicio_range'),
            'data_fim_range' => $get('data_fim_range'),
            'dias_semana_range' => $get('dias_semana_range'),
            'intervalo_semanas' => $get('intervalo_semanas'),
            'ignorar_fins_de_semana' => $get('ignorar_fins_de_semana'),
            'ignorar_feriados' => $get('ignorar_feriados'),
            'ignorar_dias_nao_letivos' => $get('ignorar_dias_nao_letivos'),
        ];

        $simulacao = app(PreceptoriaAgendaService::class)->simular(
            self::regras($estado),
            $get('professor_id') ? (int) $get('professor_id') : null,
            $get('hora_inicio'),
            $get('hora_fim'),
            $get('matricula_id') ? (int) $get('matricula_id') : null,
        );

        $dia = fn (string $data) => Carbon::parse($data)->format('d/m');
        $total = count($simulacao['datas']);

        $linhas = [];
        $linhas[] = $total === 0
            ? '<strong>Nenhuma preceptoria será criada com estas regras.</strong>'
            : sprintf('<strong>%d preceptoria(s) serão criadas</strong>: %s', $total, e(self::resumirDatas($simulacao['datas'], $dia)));

        if ($total > PreceptoriaAgendaService::LIMITE_SERIE) {
            $linhas[] = sprintf('<span style="color:#b91c1c">Acima do limite de %d por vez; reduza a janela.</span>', PreceptoriaAgendaService::LIMITE_SERIE);
        }

        $fimDeSemana = collect($simulacao['ignoradas'])->where('tipo', PreceptoriaAgendaService::TIPO_FIM_DE_SEMANA)->count();
        $especiais = collect($simulacao['ignoradas'])->where('tipo', '!=', PreceptoriaAgendaService::TIPO_FIM_DE_SEMANA);

        if ($fimDeSemana > 0) {
            $linhas[] = e("Ignorados: {$fimDeSemana} dia(s) de fim de semana.");
        }

        if ($especiais->isNotEmpty()) {
            $linhas[] = 'Ignorados: '.e($especiais->map(fn (array $i) => "{$dia($i['data'])} ({$i['motivo']})")->implode('; ')).'.';
        }

        if ($simulacao['conflitos'] !== []) {
            $linhas[] = '<span style="color:#b45309">Já existe preceptoria deste professor em: '
                .e(collect($simulacao['conflitos'])->keys()->map($dia)->implode(', '))
                .' (essas datas serão puladas).</span>';
        }

        $soDoAluno = array_diff_key($simulacao['conflitos_aluno'], $simulacao['conflitos']);
        if ($soDoAluno !== []) {
            $linhas[] = '<span style="color:#b45309">O aluno já tem preceptoria em: '
                .e(collect($soDoAluno)->keys()->map($dia)->implode(', '))
                .' (essas datas serão puladas).</span>';
        }

        return self::texto(implode('<br>', $linhas));
    }

    /**
     * @param  array<int, string>  $datas
     */
    private static function resumirDatas(array $datas, callable $dia): string
    {
        $mostradas = array_map($dia, array_slice($datas, 0, 12));

        return implode(', ', $mostradas).(count($datas) > 12 ? sprintf(' … e mais %d', count($datas) - 12) : '');
    }

    private static function texto(string $html): HtmlString
    {
        return new HtmlString('<div style="font-size:.875rem;line-height:1.5">'.$html.'</div>');
    }
}
