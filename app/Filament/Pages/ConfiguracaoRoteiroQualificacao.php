<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Jobs\ReavaliarQualificacaoJob;
use App\Models\QualificacaoRoteiroConfiguracao;
use App\Support\HelpContent;
use App\Support\Qualificacao\CampoQualificacao;
use App\Support\Qualificacao\Roteiro;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Roteiro de qualificação do lead (SDR, Lote 2): a secretaria ajusta o texto sugerido de cada pergunta, a ordem da
 * conversa, quais campos são necessários para marcar a visita e quais estão ativos. Os campos em si são fixos (a lista é
 * fechada de propósito: sem renda exata nem dados de saúde); ver {@see Roteiro}.
 */
class ConfiguracaoRoteiroQualificacao extends Page implements HasForms
{
    use HasAjudaAction;
    use HasPageShield;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $navigationLabel = 'Roteiro de qualificação';

    protected static ?string $title = 'Roteiro de qualificação do lead';

    protected static ?string $slug = 'crm/roteiro-qualificacao';

    public ?array $data = [];

    public function mount(): void
    {
        $this->getSchema('content')->fill(['campos' => $this->itensDoFormulario()]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campos do roteiro')
                    ->description('Arraste (ou use as setas) para mudar a ordem em que a conversa percorre os campos. A pergunta é o texto que o Copiloto IA reescreve com naturalidade quando o objetivo é "Conhecer a família". Os campos em si não mudam: renda exata e informações de saúde não fazem parte do roteiro.')
                    ->schema([
                        Repeater::make('campos')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('chave'),
                                Toggle::make('ativo')
                                    ->label('Usar este campo no roteiro')
                                    ->default(true),
                                Toggle::make('gate')
                                    ->label('Necessário para marcar a visita')
                                    ->disabled(fn (Get $get): bool => in_array($get('chave'), Roteiro::FORA_DO_GATE, true))
                                    ->helperText(fn (Get $get): ?string => in_array($get('chave'), Roteiro::FORA_DO_GATE, true) ? 'A mensalidade nunca é exigida para marcar a visita.' : null),
                                Textarea::make('pergunta')
                                    ->label('Pergunta sugerida')
                                    ->rows(2)
                                    ->required()
                                    ->maxLength(Roteiro::MAX_PERGUNTA)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $this->tituloDoItem($state)),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salvar')
                ->label('Salvar roteiro')
                ->icon('heroicon-m-check')
                ->color('primary')
                ->action('salvar'),
            Action::make('restaurar')
                ->label('Restaurar padrão')
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Descarta as personalizações e volta à ordem, às perguntas e aos campos necessários originais do sistema.')
                ->action('restaurarPadrao'),
            $this->ajudaAction('Roteiro de qualificação', HelpContent::make('🧭', 'Roteiro de qualificação do lead', 'Defina o que a escola precisa saber da família antes de marcar a visita e o texto das perguntas que o Copiloto IA usa para descobrir.')
                ->passos('🚀 Passo a passo', [
                    'Arraste os campos (ou use as setas) para a ordem em que a conversa deve percorrê-los.',
                    'Em cada campo, ajuste o texto da pergunta sugerida.',
                    'Marque "Necessário para marcar a visita" nos campos que o lead precisa ter respondido para ficar Qualificado.',
                    'Desligue "Usar este campo no roteiro" para tirar um campo da conversa (ele some do painel e das perguntas).',
                    'Clique em Salvar roteiro. A mudança vale na hora e os leads em andamento são reavaliados em segundo plano.',
                ])
                ->secao('📚 Como funciona?', [
                    ['🎯', 'Necessário para a visita', 'Quando todos os campos marcados estão respondidos (ou a família preferiu não informar), o lead fica "Qualificado". Precisa haver ao menos um.'],
                    ['💬', 'Pergunta sugerida', 'É o que o Copiloto IA reescreve de forma natural no objetivo "Conhecer a família". O consultor ainda revisa a mensagem antes de abrir o WhatsApp.'],
                    ['💰', 'Mensalidade', 'Nunca é exigida e nunca entra sozinha nas perguntas: pergunte por último, e só se a família abrir o assunto.'],
                    ['🔒', 'O que não muda', 'A lista de campos é fechada: renda exata e informações de saúde da criança não entram no roteiro.'],
                    ['↩️', 'Restaurar padrão', 'Volta à ordem, às perguntas e aos campos necessários de fábrica.'],
                ])
                ->alerta('Evite citar valores, descontos ou promessas nas perguntas: a IA as repete para as famílias.')),
        ];
    }

    public function salvar(): void
    {
        $state = $this->getSchema('content')->getState();
        $campos = $this->normalizar((array) ($state['campos'] ?? []));

        if (collect($campos)->doesntContain(fn (array $campo): bool => $campo['ativo'] && $campo['gate'])) {
            Notification::make()
                ->title('Marque ao menos um campo')
                ->body('Pelo menos um campo ativo precisa ser "Necessário para marcar a visita"; senão nenhum lead teria como ficar Qualificado.')
                ->warning()
                ->send();

            return;
        }

        $antes = $this->assinaturaDoGate();

        QualificacaoRoteiroConfiguracao::create([
            'valores' => ['campos' => $campos],
            'atualizado_por' => auth()->id(),
        ]);

        $this->reavaliarSeMudou($antes);

        Notification::make()
            ->title('Roteiro salvo')
            ->body('Vale a partir de agora. Se mudou o que é necessário para a visita, os leads em andamento são reavaliados em segundo plano.')
            ->success()
            ->send();
    }

    public function restaurarPadrao(): void
    {
        $antes = $this->assinaturaDoGate();

        QualificacaoRoteiroConfiguracao::query()->delete();
        QualificacaoRoteiroConfiguracao::limparCache();

        $this->getSchema('content')->fill(['campos' => $this->itensDoFormulario()]);
        $this->reavaliarSeMudou($antes);

        Notification::make()->title('Roteiro padrão restaurado')->success()->send();
    }

    /**
     * Os campos como o formulário os mostra (um item por campo, na ordem atual).
     *
     * @return list<array{chave: string, ativo: bool, gate: bool, pergunta: string}>
     */
    private function itensDoFormulario(): array
    {
        return array_map(fn (CampoQualificacao $campo): array => [
            'chave' => $campo->chave,
            'ativo' => $campo->ativo,
            'gate' => $campo->gate,
            'pergunta' => $campo->pergunta,
        ], Roteiro::todos());
    }

    /**
     * Estado do formulário no formato salvo: a posição do item vira a ordem (de 10 em 10), e só entram os campos que o
     * roteiro conhece. A mensalidade nunca vai para o gate, qualquer que seja o estado do formulário.
     *
     * @param  array<int|string, mixed>  $itens
     * @return array<string, array{pergunta: string, ordem: int, gate: bool, ativo: bool}>
     */
    private function normalizar(array $itens): array
    {
        $conhecidos = array_map(fn (CampoQualificacao $campo): string => $campo->chave, Roteiro::padroes());
        $campos = [];

        foreach (array_values($itens) as $posicao => $item) {
            $chave = (string) ($item['chave'] ?? '');

            if (! in_array($chave, $conhecidos, true)) {
                continue;
            }

            $campos[$chave] = [
                'pergunta' => mb_substr(trim((string) ($item['pergunta'] ?? '')), 0, Roteiro::MAX_PERGUNTA),
                'ordem' => ($posicao + 1) * 10,
                'gate' => ! in_array($chave, Roteiro::FORA_DO_GATE, true) && (bool) ($item['gate'] ?? false),
                'ativo' => (bool) ($item['ativo'] ?? true),
            ];
        }

        return $campos;
    }

    /** Título do item do formulário: o rótulo do campo, a dimensão e a situação. */
    private function tituloDoItem(array $state): ?string
    {
        $campo = Roteiro::campo((string) ($state['chave'] ?? ''));

        if ($campo === null) {
            return null;
        }

        return $campo->rotulo
            .' ('.Roteiro::DIMENSOES[$campo->dimensao].')'
            .(($state['ativo'] ?? true) ? '' : ' · desligado')
            .(($state['ativo'] ?? true) && ($state['gate'] ?? false) ? ' · necessário para a visita' : '');
    }

    /** Quais campos ativos são necessários para a visita: se isto mudar, o resultado dos leads existentes muda. */
    private function assinaturaDoGate(): string
    {
        return implode(',', array_map(
            fn (CampoQualificacao $campo): string => $campo->chave.($campo->gate ? '*' : ''),
            Roteiro::campos(),
        ));
    }

    private function reavaliarSeMudou(string $assinaturaAnterior): void
    {
        if ($assinaturaAnterior !== $this->assinaturaDoGate()) {
            ReavaliarQualificacaoJob::dispatch();
        }
    }
}
