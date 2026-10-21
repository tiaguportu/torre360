<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoTratamentos\Schemas;

use App\Enums\TipoTratamentoPonto;
use App\Models\Funcionario;
use App\Models\PontoRegistro;
use App\Support\FuncionarioDoUsuario;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PontoTratamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        $decide = fn (): bool => (bool) auth()->user()?->can('Aprovar:PontoTratamento');

        return $schema
            ->components([
                Section::make('Ajuste')
                    ->description('A marcação original nunca é alterada: o ajuste vive ao lado dela e só conta na apuração depois de aprovado.')
                    ->columns(2)
                    ->schema([
                        Select::make('funcionario_id')
                            ->label('Funcionário')
                            ->required()
                            ->searchable()
                            ->live()
                            ->default(fn (): ?int => $decide() ? null : FuncionarioDoUsuario::para(auth()->user())?->id)
                            ->disabled(fn (): bool => ! $decide())
                            ->dehydrated()
                            ->getSearchResultsUsing(fn (string $search): array => $decide()
                                ? Funcionario::query()->with('pessoa')->whereNull('data_desligamento')
                                    ->whereHas('pessoa', fn ($q) => $q->where('nome', 'like', '%'.addcslashes($search, '%_\\').'%'))
                                    ->limit(30)->get()
                                    ->mapWithKeys(fn (Funcionario $f): array => [$f->id => ($f->pessoa?->nome ?? 'Funcionário').' — '.$f->cargo])->all()
                                : [])
                            ->getOptionLabelUsing(fn ($value): ?string => ($f = Funcionario::query()->with('pessoa')->find($value)) ? ($f->pessoa?->nome ?? 'Funcionário').' — '.$f->cargo : null)
                            ->columnSpanFull(),
                        Select::make('tipo')
                            ->label('O que ajustar')
                            ->options(TipoTratamentoPonto::class)
                            ->required()
                            ->live(),
                        DatePicker::make('data')
                            ->label('Dia do ajuste')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(today())
                            ->required()
                            ->live(),
                        DateTimePicker::make('momento')
                            ->label('Horário da marcação esquecida')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i')
                            ->maxDate(now())
                            ->visible(fn (Get $get): bool => self::ehTipo($get('tipo'), TipoTratamentoPonto::Inclusao))
                            ->required(fn (Get $get): bool => self::ehTipo($get('tipo'), TipoTratamentoPonto::Inclusao)),
                        Select::make('registro_id')
                            ->label('Marcação a desconsiderar')
                            ->options(fn (Get $get): array => self::marcacoesDoDia($get('funcionario_id'), $get('data')))
                            ->visible(fn (Get $get): bool => self::ehTipo($get('tipo'), TipoTratamentoPonto::Desconsiderar))
                            ->required(fn (Get $get): bool => self::ehTipo($get('tipo'), TipoTratamentoPonto::Desconsiderar))
                            ->helperText('Escolha o funcionário e o dia para listar as marcações.'),
                        TextInput::make('minutos_abono')
                            ->label('Minutos a abonar')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->suffix('min')
                            ->visible(fn (Get $get): bool => self::ehTipo($get('tipo'), TipoTratamentoPonto::Abono))
                            ->required(fn (Get $get): bool => self::ehTipo($get('tipo'), TipoTratamentoPonto::Abono))
                            ->helperText('480 min = 8 horas.'),
                        Textarea::make('motivo')
                            ->label('Motivo')
                            ->required()
                            ->minLength(10)
                            ->maxLength(500)
                            ->rows(3)
                            ->helperText('Fica criptografado e só aparece para o trabalhador e para quem decide. Se for atestado, não escreva o diagnóstico: basta informar que há atestado.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * O estado de um Select com enum chega como o próprio enum ou como o valor, conforme o ponto do ciclo da tela.
     */
    private static function ehTipo(mixed $estado, TipoTratamentoPonto $tipo): bool
    {
        return ($estado instanceof TipoTratamentoPonto ? $estado : TipoTratamentoPonto::tryFrom((string) $estado)) === $tipo;
    }

    /**
     * @return array<int, string>
     */
    private static function marcacoesDoDia(mixed $funcionarioId, mixed $data): array
    {
        if (! $funcionarioId || ! $data) {
            return [];
        }

        return PontoRegistro::query()
            ->where('funcionario_id', $funcionarioId)
            ->whereDate('momento', $data)
            ->orderBy('momento')
            ->get()
            ->mapWithKeys(fn (PontoRegistro $r): array => [$r->id => $r->momento->format('H:i:s')." (NSR {$r->nsr})"])
            ->all();
    }
}
