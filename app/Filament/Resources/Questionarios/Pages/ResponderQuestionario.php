<?php

namespace App\Filament\Resources\Questionarios\Pages;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use App\Models\Questionario;
use App\Models\QuestionarioPerguntaResposta;
use App\Models\QuestionarioResposta;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ResponderQuestionario extends Page
{
    protected static string $resource = QuestionarioResource::class;

    protected static string $view = 'filament.resources.questionarios.pages.responder-questionario';

    public ?Questionario $record = null;

    public $data = [];

    public function mount(Questionario $record): void
    {
        $this->record = $record;

        // Verificar se usuário já respondeu (se não for anônimo)
        if (! $this->record->is_anonimo && Auth::check()) {
            $jaRespondeu = QuestionarioResposta::where('questionario_id', $this->record->id)
                ->where('user_id', Auth::id())
                ->where('status', 'enviado')
                ->exists();

            if ($jaRespondeu) {
                Notification::make()
                    ->title('Você já respondeu este questionário.')
                    ->warning()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            }
        }
    }

    public function form(Schema $schema): Schema
    {
        $steps = [];

        foreach ($this->record->blocos as $bloco) {
            $perguntasSchema = [];

            foreach ($bloco->perguntas as $pergunta) {
                $field = match ($pergunta->tipo) {
                    'discursiva' => Textarea::make("pergunta_{$pergunta->id}")
                        ->label($pergunta->enunciado)
                        ->required($pergunta->is_obrigatoria),

                    'objetiva', 'likert' => Radio::make("pergunta_{$pergunta->id}")
                        ->label($pergunta->enunciado)
                        ->options($this->formatOptions($pergunta))
                        ->required($pergunta->is_obrigatoria),

                    'multipla_escolha' => CheckboxList::make("pergunta_{$pergunta->id}")
                        ->label($pergunta->enunciado)
                        ->options($this->formatOptions($pergunta))
                        ->required($pergunta->is_obrigatoria),

                    default => TextInput::make("pergunta_{$pergunta->id}")->label($pergunta->enunciado),
                };

                $perguntasSchema[] = $field;
            }

            $steps[] = Wizard\Step::make($bloco->titulo)
                ->description($bloco->descricao)
                ->schema($perguntasSchema);
        }

        return $schema->components([
            Wizard::make($steps)
                ->submitAction(view('filament.resources.questionarios.pages.responder-questionario-submit-button')),
        ])->statePath('data');
    }

    protected function formatOptions($pergunta): array
    {
        if ($pergunta->tipo === 'likert') {
            return [
                '1' => '1 - Muito Insatisfeito',
                '2' => '2 - Insatisfeito',
                '3' => '3 - Neutro',
                '4' => '4 - Satisfeito',
                '5' => '5 - Muito Satisfeito',
            ];
        }

        $options = [];
        if (is_array($pergunta->opcoes)) {
            foreach ($pergunta->opcoes as $opcao) {
                $options[$opcao['label']] = $opcao['label'];
            }
        }

        return $options;
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $respostaPrincipal = QuestionarioResposta::create([
            'questionario_id' => $this->record->id,
            'user_id' => $this->record->is_anonimo ? null : Auth::id(),
            'perfil_institucional' => Auth::user()?->roles()->first()?->name ?? 'visitante',
            'inicio_preenchimento' => now(), // Idealmente capturar no mount
            'fim_preenchimento' => now(),
            'status' => 'enviado',
        ]);

        foreach ($data as $key => $valor) {
            if (str_starts_with($key, 'pergunta_')) {
                $perguntaId = str_replace('pergunta_', '', $key);

                QuestionarioPerguntaResposta::create([
                    'questionario_resposta_id' => $respostaPrincipal->id,
                    'questionario_pergunta_id' => $perguntaId,
                    'resposta_texto' => is_string($valor) ? $valor : null,
                    'resposta_json' => is_array($valor) ? $valor : null,
                ]);
            }
        }

        Notification::make()
            ->title('Questionário enviado com sucesso!')
            ->success()
            ->send();

        $this->redirect($this->getResource()::getUrl('index'));
    }

    public function getTitle(): string
    {
        return $this->record->titulo;
    }
}
