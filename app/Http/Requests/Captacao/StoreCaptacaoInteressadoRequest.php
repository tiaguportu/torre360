<?php

namespace App\Http\Requests\Captacao;

use App\Rules\RecaptchaV3;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaptacaoInteressadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Quem preenche
            'tipo_preenchimento' => ['required', 'in:proprio,responsavel'],

            // Dados do responsável
            'responsavel_nome' => ['required_if:tipo_preenchimento,responsavel', 'nullable', 'string', 'max:255'],
            'responsavel_cpf' => ['nullable', 'string', 'max:20'],
            'responsavel_telefone' => ['required', 'string', 'max:30'],
            'responsavel_email' => ['required', 'email', 'max:255'],

            // Múltiplos alunos
            'alunos' => ['required', 'array', 'min:1'],
            'alunos.*.nome' => ['required', 'string', 'max:255'],
            'alunos.*.data_nascimento' => ['nullable', 'date'],
            'alunos.*.vinculo' => ['nullable', 'string', 'max:100'],
            'alunos.*.unidade_id' => ['nullable', 'exists:unidade,id'],
            'alunos.*.serie_id' => ['nullable', 'exists:serie,id'],
            'alunos.*.turno_preferencia' => ['nullable', 'string', 'in:Manhã,Tarde,Integral,Sem preferência'],

            // Extras
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'como_conheceu' => ['nullable', 'exists:origem_interessado,id'],

            // LGPD: o aceite do tratamento dos dados (inclusive de menores) é obrigatório e fica registrado na pessoa.
            'consentimento' => ['accepted'],

            'recaptcha_token' => [new RecaptchaV3($this->ip())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_preenchimento.required' => 'Informe quem está preenchendo.',
            'responsavel_nome.required_if' => 'Informe o nome do responsável.',
            'responsavel_telefone.required' => 'O telefone / WhatsApp para contato é obrigatório.',
            'responsavel_email.required' => 'O e-mail para contato é obrigatório.',
            'responsavel_email.email' => 'Informe um e-mail válido.',
            'alunos.required' => 'Informe os dados de ao menos um aluno.',
            'alunos.*.nome.required' => 'Informe o nome completo do aluno.',
            'consentimento.accepted' => 'Para enviar, é preciso autorizar o uso dos dados para o atendimento de admissão.',
        ];
    }
}
