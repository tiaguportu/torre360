<?php

declare(strict_types=1);

namespace App\Filament\Resources\ParecerDescritivos\Schemas;

use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\ParecerDescritivo;
use App\Services\ParecerDescritivoIaService;
use App\Support\EscopoProfessor;
use App\Support\OpcoesMatricula;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ParecerDescritivoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Parecer')
                    ->columns(2)
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Aluno')
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('etapa_avaliativa_id', null))
                            ->getSearchResultsUsing(fn (string $search): array => OpcoesMatricula::buscar($search, auth()->user()))
                            ->getOptionLabelUsing(fn ($value): ?string => OpcoesMatricula::rotuloPorId($value))
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! EscopoProfessor::podeVerMatricula(auth()->user(), Matricula::find($value))) {
                                        $fail('Você não tem acesso a este aluno.');
                                    }
                                },
                            ])
                            ->disabledOn(['edit', 'view']),
                        Select::make('etapa_avaliativa_id')
                            ->label('Etapa avaliativa')
                            ->options(fn (Get $get): array => EtapaAvaliativa::query()
                                ->where('turma_id', Matricula::find($get('matricula_id'))?->turma_id ?? 0)
                                ->orderByDesc('data_inicio')
                                ->pluck('nome', 'id')
                                ->all())
                            ->required()
                            ->disabledOn(['edit', 'view']),
                        Placeholder::make('situacao')
                            ->label('Situação')
                            ->content(fn (?ParecerDescritivo $record): string => ($record?->status?->getLabel() ?? '—').($record?->gerado_com_ia ? ' · rascunho assistido por IA' : ''))
                            ->visibleOn(['edit', 'view']),
                    ]),
                Section::make('Registros desta etapa')
                    ->description('É o que o sistema reúne para ajudar a escrever (e o que seria enviado à IA, sem o nome do aluno).')
                    ->collapsible()
                    ->visibleOn(['edit', 'view'])
                    ->schema([
                        Placeholder::make('evidencias')
                            ->hiddenLabel()
                            ->content(function (?ParecerDescritivo $record): HtmlString {
                                if (! $record) {
                                    return new HtmlString('');
                                }

                                $contexto = app(ParecerDescritivoIaService::class)->montarContexto($record);

                                $habilidades = collect($contexto['habilidades'])->map(fn (array $h): string => sprintf(
                                    '<li><strong>%s</strong> %s: %s</li>',
                                    e((string) $h['codigo']),
                                    e((string) $h['nome']),
                                    e((string) $h['conceito']),
                                ))->implode('');

                                $evidencias = collect($contexto['evidencias'])->map(fn (array $e): string => sprintf(
                                    '<li>%s%s</li>',
                                    e((string) $e['titulo']),
                                    $e['habilidades'] ? ' <span class="opacity-70">('.e(implode(', ', $e['habilidades'])).')</span>' : '',
                                ))->implode('');

                                return new HtmlString(
                                    '<div class="space-y-2 text-sm">'
                                    .'<div><strong>Conceitos por habilidade</strong><ul class="list-disc pl-5">'.($habilidades ?: '<li>Nenhum conceito lançado nesta etapa.</li>').'</ul></div>'
                                    .'<div><strong>Evidências do portfólio</strong><ul class="list-disc pl-5">'.($evidencias ?: '<li>Nenhuma evidência registrada nesta etapa.</li>').'</ul></div>'
                                    .'</div>'
                                );
                            }),
                    ]),
                Section::make('Texto do parecer')
                    ->schema([
                        Textarea::make('texto')
                            ->label('Parecer')
                            ->rows(14)
                            ->maxLength(6000)
                            ->helperText('Escreva com linguagem simples e acolhedora: o que o aluno já consegue, o que está em desenvolvimento e os próximos passos. Não compare com colegas nem use rótulos.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
