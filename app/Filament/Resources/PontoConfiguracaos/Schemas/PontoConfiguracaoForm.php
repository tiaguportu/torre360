<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoConfiguracaos\Schemas;

use App\Enums\ModoPonto;
use App\Models\PontoConfiguracao;
use App\Services\Ponto\ConformidadePontoService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PontoConfiguracaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Unidade e modo')
                    ->columns(2)
                    ->schema([
                        Select::make('unidade_id')
                            ->label('Unidade (estabelecimento)')
                            ->relationship('unidade', 'nome')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabledOn('edit')
                            ->unique(ignoreRecord: true)
                            ->helperText('Cada unidade tem a sua sequência de registros (NSR). Depois de criada, a unidade não muda.'),
                        Select::make('modo')
                            ->label('Modo de operação')
                            ->options(ModoPonto::class)
                            ->default(ModoPonto::ControleInterno->value)
                            ->required()
                            ->live()
                            ->helperText('O modo oficial só é aceito quando todos os requisitos legais da Portaria MTP 671/2021 estão cumpridos (veja "Conformidade" abaixo).'),
                        Toggle::make('ativo')->label('Registro de ponto ativo')->default(true),
                        Toggle::make('exige_hora_legal')
                            ->label('Conferir a Hora Legal Brasileira em cada marcação')
                            ->default(true)
                            ->helperText('A marcação nunca é bloqueada; quando a conferência falha, ela é sinalizada.'),
                        TextInput::make('tolerancia_diaria_minutos')
                            ->label('Tolerância diária (minutos)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10)
                            ->default(10)
                            ->required()
                            ->helperText('A CLT (art. 58, § 1º) limita a tolerância a 10 minutos por dia.'),
                    ]),
                Section::make('REP-P: registrador via programa')
                    ->description('Exigências do registro por programa (Portaria MTP 671/2021, arts. 78, 89 e 91).')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::ehModo($get('modo'), ModoPonto::RepP))
                    ->schema([
                        TextInput::make('registro_inpi')
                            ->label('Número do registro do programa no INPI')
                            ->maxLength(60)
                            ->helperText('Certificado de registro de programa de computador. É do software e é obtido por quem o desenvolve.'),
                        DatePicker::make('atestado_tecnico_data')
                            ->label('Data de emissão do Atestado Técnico')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(today()),
                        FileUpload::make('atestado_tecnico_path')
                            ->label('Atestado Técnico e Termo de Responsabilidade (PDF)')
                            ->disk('local')
                            ->directory('ponto/atestados')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->previewable(false)
                            ->downloadable(false)
                            ->openable(false)
                            ->columnSpanFull(),
                        Placeholder::make('atestado_atual')
                            ->label('Atestado já enviado')
                            ->content(fn (?PontoConfiguracao $record): HtmlString => $record && $record->atestado_tecnico_path
                                ? new HtmlString('<a href="'.e(route('ponto.atestado', $record)).'" target="_blank" rel="noopener" class="text-primary-600 underline">Abrir o PDF</a>')
                                : new HtmlString('<span>—</span>'))
                            ->visibleOn('edit'),
                        Toggle::make('atestado_assinatura_conferida')
                            ->label('Conferi a assinatura qualificada (ICP-Brasil) do atestado')
                            ->helperText('Valide o PDF em um verificador de assinaturas ICP-Brasil antes de marcar.'),
                    ]),
                Section::make('REP-A: registrador alternativo')
                    ->description('Exige acordo ou convenção coletiva de trabalho (Portaria MTP 671/2021, art. 77).')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::ehModo($get('modo'), ModoPonto::RepA))
                    ->schema([
                        TextInput::make('acordo_coletivo_referencia')
                            ->label('Referência do acordo ou convenção coletiva')
                            ->maxLength(255),
                        DatePicker::make('acordo_coletivo_vigencia_fim')
                            ->label('Fim da vigência')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ]),
                Section::make('Conformidade')
                    ->description('O que falta para o modo escolhido e como resolver.')
                    ->schema([
                        Placeholder::make('conformidade')
                            ->hiddenLabel()
                            ->content(fn (Get $get, ?PontoConfiguracao $record): HtmlString => self::resumoDeConformidade($get, $record)),
                    ]),
            ]);
    }

    /**
     * O estado de um Select com enum chega como o próprio enum ou como o valor, conforme o ponto do ciclo da tela.
     */
    private static function ehModo(mixed $estado, ModoPonto $modo): bool
    {
        return ($estado instanceof ModoPonto ? $estado : ModoPonto::tryFrom((string) $estado)) === $modo;
    }

    private static function resumoDeConformidade(Get $get, ?PontoConfiguracao $record): HtmlString
    {
        $anexo = $get('atestado_tecnico_path');

        $simulada = new PontoConfiguracao([
            'unidade_id' => $record?->unidade_id ?? $get('unidade_id'),
            'modo' => $get('modo') ?: ModoPonto::ControleInterno->value,
            'registro_inpi' => $get('registro_inpi'),
            'atestado_tecnico_path' => is_array($anexo) ? (collect($anexo)->filter()->isNotEmpty() ? 'enviado' : null) : $anexo,
            'atestado_tecnico_data' => $get('atestado_tecnico_data'),
            'atestado_assinatura_conferida' => (bool) $get('atestado_assinatura_conferida'),
            'acordo_coletivo_referencia' => $get('acordo_coletivo_referencia'),
            'acordo_coletivo_vigencia_fim' => $get('acordo_coletivo_vigencia_fim'),
        ]);

        $servico = app(ConformidadePontoService::class);
        $pendencias = $servico->pendencias($simulada);

        if (! $simulada->modo->ehOficial()) {
            $html = '<p><strong>Controle interno.</strong> Os registros são íntegros (sequência numerada e encadeada por hash, ajustes sem alterar o original), mas este modo <strong>não substitui um REP</strong> perante a fiscalização do trabalho. Estabelecimentos com mais de 20 trabalhadores precisam de REP-C, REP-A ou REP-P.</p>';
        } elseif ($pendencias === []) {
            $html = '<p class="text-success-600"><strong>Todos os requisitos estão cumpridos.</strong></p>';
        } else {
            $html = '<p class="text-danger-600"><strong>'.count($pendencias).' requisito(s) pendente(s). Enquanto houver pendência, o modo não pode ser salvo.</strong></p><ul class="mt-2 list-disc space-y-2 pl-5">';
            foreach ($pendencias as $pendencia) {
                $html .= '<li><strong>'.e($pendencia['titulo']).'</strong><br><span class="text-sm">'.e($pendencia['como_resolver']).'</span></li>';
            }
            $html .= '</ul>';
        }

        if ($record) {
            foreach ($servico->alertas($record) as $alerta) {
                $html .= '<p class="mt-3 text-warning-700">⚠️ '.e($alerta['mensagem']).'</p>';
            }
        }

        return new HtmlString($html);
    }
}
