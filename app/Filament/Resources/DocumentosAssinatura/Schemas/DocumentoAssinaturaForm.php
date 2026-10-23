<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosAssinatura\Schemas;

use App\Enums\ContextoDocumento;
use App\Models\TemplateDocumento;
use App\Services\Documentos\RegistrosDoContexto;
use App\Services\Documentos\SignatariosSugeridosService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Criação do documento para assinatura: o modelo, o registro de onde vêm os dados e quem assina. A lista de assinantes
 * já vem sugerida (responsáveis financeiros e representante da escola, ou o funcionário), e quem cria confere e ajusta.
 */
class DocumentoAssinaturaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Documento')
                    ->description('Escolha o modelo e de quem são os dados. O texto sai do modelo ativo, com as variáveis preenchidas; para mudar o texto, edite o modelo em Modelos de Documentos.')
                    ->columns(2)
                    ->schema([
                        Select::make('template_documento_id')
                            ->label('Modelo')
                            ->options(fn (): array => self::modelos())
                            ->searchable()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('documentavel_id', null);
                                $set('assinantes', []);
                            })
                            ->helperText('Só aparecem modelos ativos. Os modelos novos nascem inativos: revise o texto com o jurídico e ative.'),
                        Select::make('documentavel_id')
                            ->label(fn (Get $get): string => self::contexto($get('template_documento_id'))?->getLabel() ?? 'Registro')
                            ->placeholder(fn (Get $get): string => filled($get('template_documento_id')) ? 'Busque pelo nome' : 'Escolha o modelo primeiro')
                            ->searchable()
                            ->live()
                            ->required()
                            ->disabled(fn (Get $get): bool => ! filled($get('template_documento_id')))
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => ($contexto = self::contexto($get('template_documento_id'))) ? RegistrosDoContexto::buscar($contexto, $search) : [])
                            ->getOptionLabelUsing(fn ($value, Get $get): ?string => ($contexto = self::contexto($get('template_documento_id'))) ? RegistrosDoContexto::rotulo($contexto, $value) : null)
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                self::sugerirAssinantes($get('template_documento_id'), $state, $set);
                            }),
                        TextInput::make('titulo')
                            ->label('Título (opcional)')
                            ->helperText('Se ficar em branco, usa o nome do modelo e o do aluno ou funcionário.')
                            ->maxLength(200),
                        DatePicker::make('expira_em')
                            ->label('Prazo para assinar (opcional)')
                            ->helperText('Passado o prazo sem todas as assinaturas, o documento expira e sai do Assinafy.')
                            ->native(false)
                            ->minDate(today()),
                    ]),
                Section::make('Quem assina')
                    ->description('Cada pessoa recebe um e-mail do Assinafy com o link para assinar. Confira os nomes e os e-mails: a assinatura só vale para quem recebe o link.')
                    ->schema([
                        Repeater::make('assinantes')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('nome')->label('Nome')->required()->maxLength(160),
                                TextInput::make('email')->label('E-mail')->email()->required()->maxLength(160),
                                TextInput::make('papel')->label('Papel')->placeholder('Ex.: Responsável financeiro')->maxLength(40),
                            ])
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => filled($state['nome'] ?? null) ? $state['nome'] : null)
                            ->addActionLabel('Adicionar assinante')
                            ->minItems(1)
                            ->maxItems(10)
                            ->defaultItems(0)
                            ->required(),
                    ]),
            ]);
    }

    /**
     * Modelos que podem ir para assinatura: os ativos. O modelo de funcionário só aparece para quem vê Admissão e
     * Desligamento (RH).
     *
     * @return array<int, string>
     */
    private static function modelos(): array
    {
        $podeRh = (bool) auth()->user()?->can('View:ProcessoFuncionario');

        return TemplateDocumento::query()
            ->where('is_ativo', true)
            ->orderBy('nome')
            ->get()
            ->filter(fn (TemplateDocumento $modelo): bool => $podeRh || $modelo->contexto() !== ContextoDocumento::Funcionario)
            ->mapWithKeys(fn (TemplateDocumento $modelo): array => [$modelo->id => $modelo->nome.' — '.$modelo->contexto()->getLabel()])
            ->all();
    }

    public static function contexto(mixed $modeloId): ?ContextoDocumento
    {
        return filled($modeloId) ? TemplateDocumento::query()->find($modeloId)?->contexto() : null;
    }

    private static function sugerirAssinantes(mixed $modeloId, mixed $registroId, Set $set): void
    {
        $set('assinantes', []);

        $contexto = self::contexto($modeloId);

        if (! $contexto || ! filled($registroId)) {
            return;
        }

        $registro = $contexto->modelo()::query()->find($registroId);

        if (! $registro) {
            return;
        }

        $sugestao = app(SignatariosSugeridosService::class)->para($registro);
        $set('assinantes', $sugestao['assinantes']);

        if ($sugestao['sem_email'] !== []) {
            Notification::make()
                ->title('Pessoas sem e-mail cadastrado')
                ->body(implode(', ', $sugestao['sem_email']).' não entraram na lista. Cadastre o e-mail na pessoa ou acrescente o assinante à mão.')
                ->warning()
                ->send();
        }
    }
}
