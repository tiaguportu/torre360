<?php

namespace App\Filament\Resources\TemplateCrachaV2S\Schemas;

use App\Enums\TemplateCrachaEntidade;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TemplateCrachaV2Form
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configurações do Crachá V2 (SVG)')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do Template')
                            ->required()
                            ->maxLength(255),
                        Select::make('tipo_entidade')
                            ->label('Tipo de Entidade')
                            ->options(TemplateCrachaEntidade::class)
                            ->required()
                            ->default(TemplateCrachaEntidade::PESSOA)
                            ->live(),
                        TextInput::make('largura')
                            ->label('Largura (px)')
                            ->required()
                            ->numeric()
                            ->default(300)
                            ->minValue(100)
                            ->maxValue(1000)
                            ->live()
                            ->helperText(fn (Get $get) => 'Equivale a aproximadamente '.round(($get('largura') ?: 0) / 3.7795, 1).' mm no PDF físico.'),
                        TextInput::make('altura')
                            ->label('Altura (px)')
                            ->required()
                            ->numeric()
                            ->default(480)
                            ->minValue(100)
                            ->maxValue(1000)
                            ->live()
                            ->helperText(fn (Get $get) => 'Equivale a aproximadamente '.round(($get('altura') ?: 0) / 3.7795, 1).' mm no PDF físico.'),
                    ])->columns(4)
                    ->columnSpanFull(),

                Section::make('Editor de Canvas')
                    ->schema([
                        Placeholder::make('editor_canvas_btn')
                            ->label('')
                            ->content(fn (?Model $record) => $record
                                ? new HtmlString('
                                    <div class="flex flex-col gap-2.5 items-start p-4 bg-slate-900 border border-slate-800 rounded-xl max-w-xl">
                                        <p class="text-sm font-semibold text-slate-200">Editor Canvas Vetorial (SVG-Edit)</p>
                                        <p class="text-xs text-slate-400">O layout deste template é editado utilizando o SVG-Edit. Ao clicar no botão abaixo, o editor será aberto em uma nova aba para desenhar no canvas.</p>
                                        <a href="'.route('template-crachas-v2.editor', $record->id).'" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg text-xs transition-all active:scale-95 shadow-md shadow-emerald-600/20">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            Editar Canvas do Crachá V2
                                        </a>
                                    </div>
                                ')
                                : new HtmlString('
                                    <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl max-w-xl text-xs text-slate-400">
                                        Por favor, salve o template pela primeira vez para liberar o botão de edição do canvas.
                                    </div>
                                ')
                            ),
                    ])->columnSpanFull(),
            ]);
    }
}
