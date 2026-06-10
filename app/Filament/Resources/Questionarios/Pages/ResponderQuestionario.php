<?php

namespace App\Filament\Resources\Questionarios\Pages;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Questionario;
use App\Models\QuestionarioPergunta;
use App\Models\QuestionarioPerguntaResposta;
use App\Models\QuestionarioResposta;
use App\Models\Turma;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ResponderQuestionario extends Page
{
    protected static string $resource = QuestionarioResource::class;

    protected string $view = 'filament.resources.questionarios.pages.responder-questionario';

    public static function canAccess(array $parameters = []): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $record = $parameters['record'] ?? null;
        if ($record) {
            if (! $record instanceof Questionario) {
                $record = Questionario::find($record);
            }

            if ($record) {
                return $record->podeSerRespondidoPor($user);
            }
        }

        return true;
    }

    public ?Questionario $record = null;

    public $data = [];

    public function mount(Questionario $record): void
    {
        $this->record = $record;

        // Verificar se o usuário faz parte do público-alvo ou se o questionário é visível
        if (! $this->record->podeSerRespondidoPor(Auth::user())) {
            Notification::make()
                ->title('Você não tem permissão para responder este questionário ou ele não está disponível.')
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));

            return;
        }

        // Verificar se usuário já respondeu (se não for anônimo) e se atingiu o limite configurado
        if (! $this->record->is_anonimo && Auth::check() && $this->record->max_respostas_por_usuario !== null) {
            $totalRespostas = QuestionarioResposta::where('questionario_id', $this->record->id)
                ->where('user_id', Auth::id())
                ->where('status', 'enviado')
                ->count();

            if ($totalRespostas >= $this->record->max_respostas_por_usuario) {
                Notification::make()
                    ->title("Você atingiu o limite máximo de {$this->record->max_respostas_por_usuario} resposta(s) para este questionário.")
                    ->warning()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));

                return;
            }
        }

        $defaultData = [];
        foreach ($this->record->blocos as $bloco) {
            foreach ($bloco->perguntas as $pergunta) {
                $defaultData["pergunta_{$pergunta->id}"] = null;
            }
        }

        $this->form->fill($defaultData);
    }

    public function form(Schema $schema): Schema
    {
        $steps = [];

        foreach ($this->record->blocos as $bloco) {
            $perguntasSchema = [];

            foreach ($bloco->perguntas as $pergunta) {
                $labelHtml = new HtmlString($pergunta->enunciado);
                $condicao = $pergunta->condicao_exibicao;

                $field = match ($pergunta->tipo) {
                    'usuarios' => Select::make("pergunta_{$pergunta->id}")
                        ->label($labelHtml)
                        ->options(fn () => User::all()->pluck('name', 'id'))
                        ->required(fn () => $pergunta->is_obrigatoria)
                        ->searchable()
                        ->live(),

                    'alunos_turma' => Select::make("pergunta_{$pergunta->id}")
                        ->label($labelHtml)
                        ->options(fn () => $this->getAlunosTurmaOptions())
                        ->required(fn () => $pergunta->is_obrigatoria)
                        ->searchable()
                        ->live(),

                    'pessoas' => Select::make("pergunta_{$pergunta->id}")
                        ->label($labelHtml)
                        ->options(fn () => Pessoa::all()->pluck('nome', 'id'))
                        ->required(fn () => $pergunta->is_obrigatoria)
                        ->searchable()
                        ->live(),

                    'discursiva' => Textarea::make("pergunta_{$pergunta->id}")
                        ->label($labelHtml)
                        ->required(fn () => $pergunta->is_obrigatoria)
                        ->live(),

                    'objetiva', 'likert' => Radio::make("pergunta_{$pergunta->id}")
                        ->label($labelHtml)
                        ->options($this->formatOptions($pergunta))
                        ->required(fn () => $pergunta->is_obrigatoria)
                        ->live(),

                    'multipla_escolha' => CheckboxList::make("pergunta_{$pergunta->id}")
                        ->label($labelHtml)
                        ->options($this->formatOptions($pergunta))
                        ->required(fn () => $pergunta->is_obrigatoria)
                        ->live(),

                    default => TextInput::make("pergunta_{$pergunta->id}")
                        ->label($labelHtml)
                        ->required(fn () => $pergunta->is_obrigatoria)
                        ->live(),
                };

                $field->validationMessages([
                    'required' => 'Esta pergunta é obrigatória.',
                ]);

                // Aplicar visibilidade condicional quando houver uma condição definida
                if (! empty($condicao) && ! empty($condicao['pergunta_id'])) {
                    $field = $this->aplicarCondicaoVisibilidade($field, $pergunta);
                }

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

    /**
     * Aplica a lógica de visibilidade condicional ao campo Filament com base na condição da pergunta.
     *
     * @param  mixed  $field  O componente Filament
     */
    protected function aplicarCondicaoVisibilidade(mixed $field, QuestionarioPergunta $pergunta): mixed
    {
        $condicao = $pergunta->condicao_exibicao;
        $perguntaRefId = $condicao['pergunta_id'] ?? null;
        $operador = $condicao['operador'] ?? 'igual';
        $valorEsperado = $condicao['valor'] ?? null;
        $chaveRef = "pergunta_{$perguntaRefId}";

        return $field->visible(function ($get) use ($chaveRef, $operador, $valorEsperado): bool {
            $valorRespondido = $get($chaveRef);

            return match ($operador) {
                'igual' => $valorRespondido == $valorEsperado,
                'diferente' => $valorRespondido != $valorEsperado,
                'contem' => is_array($valorRespondido)
                    ? in_array($valorEsperado, $valorRespondido)
                    : str_contains((string) $valorRespondido, (string) $valorEsperado),
                'nao_contem' => is_array($valorRespondido)
                    ? ! in_array($valorEsperado, $valorRespondido)
                    : ! str_contains((string) $valorRespondido, (string) $valorEsperado),
                'preenchido' => ! empty($valorRespondido),
                'nao_preenchido' => empty($valorRespondido),
                'maior_que' => (float) $valorRespondido > (float) $valorEsperado,
                'menor_que' => (float) $valorRespondido < (float) $valorEsperado,
                default => true,
            };
        });
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
        if (! $this->record->is_anonimo && Auth::check() && $this->record->max_respostas_por_usuario !== null) {
            $totalRespostas = QuestionarioResposta::where('questionario_id', $this->record->id)
                ->where('user_id', Auth::id())
                ->where('status', 'enviado')
                ->count();

            if ($totalRespostas >= $this->record->max_respostas_por_usuario) {
                Notification::make()
                    ->title("Você atingiu o limite máximo de {$this->record->max_respostas_por_usuario} resposta(s) para este questionário.")
                    ->danger()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));

                return;
            }
        }

        $data = $this->form->getState();

        // Coletar todas as respostas submetidas para verificar condições
        $respostasSubmetidas = [];
        foreach ($data as $key => $valor) {
            if (str_starts_with($key, 'pergunta_')) {
                $respostasSubmetidas[$key] = $valor;
            }
        }

        $respostaPrincipal = QuestionarioResposta::create([
            'questionario_id' => $this->record->id,
            'user_id' => $this->record->is_anonimo ? null : Auth::id(),
            'perfil_institucional' => Auth::user()?->roles()->first()?->name ?? 'visitante',
            'inicio_preenchimento' => now(),
            'fim_preenchimento' => now(),
            'status' => 'enviado',
        ]);

        // Salvar apenas respostas de perguntas que deveriam estar visíveis (condição satisfeita)
        foreach ($this->record->blocos as $bloco) {
            foreach ($bloco->perguntas as $pergunta) {
                $chave = "pergunta_{$pergunta->id}";

                // Só persiste se a pergunta deveria estar visível
                if (! $pergunta->deveSerExibida($respostasSubmetidas)) {
                    continue;
                }

                if (! array_key_exists($chave, $respostasSubmetidas)) {
                    continue;
                }

                $valor = $respostasSubmetidas[$chave];

                QuestionarioPerguntaResposta::create([
                    'questionario_resposta_id' => $respostaPrincipal->id,
                    'questionario_pergunta_id' => $pergunta->id,
                    'resposta_texto' => ! is_array($valor) ? (string) $valor : null,
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

    protected function getAlunosTurmaOptions(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        if ($user->hasRole('super_admin')) {
            return Matricula::where('situacao', 'ativa')
                ->with(['pessoa', 'turma'])
                ->get()
                ->mapWithKeys(function ($m) {
                    $nome = $m->pessoa?->nome ?? 'Sem Nome';
                    $turma = $m->turma?->nome ?? 'Sem Turma';

                    return [$m->pessoa_id => "{$nome} ({$turma})"];
                })->toArray();
        }

        $pessoa = $user->pessoa;
        if (! $pessoa) {
            return [];
        }

        $turmaIds = Matricula::where('pessoa_id', $pessoa->id)->pluck('turma_id')->toArray();

        $alunoIds = DB::table('aluno_responsavel')->where('responsavel_id', $pessoa->id)->pluck('aluno_id')->toArray();
        if (! empty($alunoIds)) {
            $turmaIds = array_merge($turmaIds, Matricula::whereIn('pessoa_id', $alunoIds)->pluck('turma_id')->toArray());
        }

        $professorTurmaIds = Turma::where('professor_conselheiro_id', $pessoa->id)
            ->orWhereHas('disciplinas', function ($q) use ($pessoa) {
                $q->where('professor_id', $pessoa->id);
            })->pluck('id')->toArray();
        if (! empty($professorTurmaIds)) {
            $turmaIds = array_merge($turmaIds, $professorTurmaIds);
        }

        $turmaIds = array_unique(array_filter($turmaIds));

        if (empty($turmaIds)) {
            return Matricula::where('situacao', 'ativa')
                ->with(['pessoa', 'turma'])
                ->get()
                ->mapWithKeys(function ($m) {
                    $nome = $m->pessoa?->nome ?? 'Sem Nome';
                    $turma = $m->turma?->nome ?? 'Sem Turma';

                    return [$m->pessoa_id => "{$nome} ({$turma})"];
                })->toArray();
        }

        return Matricula::whereIn('turma_id', $turmaIds)
            ->where('situacao', 'ativa')
            ->with(['pessoa', 'turma'])
            ->get()
            ->mapWithKeys(function ($m) {
                $nome = $m->pessoa?->nome ?? 'Sem Nome';
                $turma = $m->turma?->nome ?? 'Sem Turma';

                return [$m->pessoa_id => "{$nome} ({$turma})"];
            })->toArray();
    }

    public function getTitle(): string
    {
        return $this->record->titulo;
    }
}
