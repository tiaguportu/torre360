<?php

namespace App\Http\Requests\Captacao;

use App\Enums\Sexo;
use App\Models\Interessado;
use App\Rules\Cpf;
use App\Services\ConviteMatriculaService;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmarConviteMatriculaRequest extends FormRequest
{
    protected ?Interessado $interessado = null;

    public function authorize(): bool
    {
        return $this->getInteressado() !== null;
    }

    public function getInteressado(): ?Interessado
    {
        if ($this->interessado === null) {
            $token = (string) $this->route('token');
            $this->interessado = app(ConviteMatriculaService::class)->validarToken($token);
        }

        return $this->interessado;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dependentesIds = $this->getInteressado()?->dependentes()->pluck('id')->all() ?? [];

        return [
            'responsavel.nome' => ['required', 'string', 'max:255'],
            'responsavel.cpf' => ['required', new Cpf],
            'responsavel.data_nascimento' => ['required', 'date', 'before:today'],
            'responsavel.telefone' => ['required', 'string', 'max:30'],
            'responsavel.email' => ['nullable', 'email', 'max:255'],
            'responsavel.tipo_vinculo_id' => ['required', 'exists:tipo_vinculos,id'],
            'responsavel.is_financeiro' => ['nullable', 'boolean'],
            'responsavel.cep' => ['required', 'string', 'max:10'],
            'responsavel.logradouro' => ['required', 'string', 'max:255'],
            'responsavel.numero' => ['required', 'string', 'max:20'],
            'responsavel.complemento' => ['nullable', 'string', 'max:100'],
            'responsavel.bairro' => ['required', 'string', 'max:100'],
            'responsavel.cidade_ibge' => ['nullable', 'string', 'max:10'],

            'segundo_responsavel.nome' => ['nullable', 'string', 'max:255'],
            'segundo_responsavel.cpf' => ['nullable', 'required_with:segundo_responsavel.nome', new Cpf],
            'segundo_responsavel.tipo_vinculo_id' => ['nullable', 'required_with:segundo_responsavel.nome', 'exists:tipo_vinculos,id'],
            'segundo_responsavel.telefone' => ['nullable', 'string', 'max:30'],
            'segundo_responsavel.email' => ['nullable', 'email', 'max:255'],
            'segundo_responsavel.is_financeiro' => ['nullable', 'boolean'],
            'segundo_responsavel.percentual' => ['nullable', 'integer', 'between:1,99'],

            'dependentes' => ['required', 'array', 'min:1'],
            'dependentes.*.id' => ['required', 'integer', 'in:'.implode(',', $dependentesIds ?: [0])],
            'dependentes.*.serie_id' => ['required', 'exists:serie,id'],
            'dependentes.*.turno_preferencia' => ['nullable', 'string', 'in:Manhã,Tarde,Integral,Sem preferência'],
            'dependentes.*.data_nascimento' => ['required', 'date', 'before:today'],
            'dependentes.*.cpf' => ['nullable', new Cpf],
            'dependentes.*.sexo' => ['nullable', 'in:'.implode(',', array_column(Sexo::cases(), 'value'))],

            'lgpd_aceite' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lgpd_aceite.accepted' => 'É necessário concordar com o tratamento dos dados para continuar.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'responsavel.cpf' => 'CPF do responsável',
            'segundo_responsavel.cpf' => 'CPF do segundo responsável',
            'dependentes.*.cpf' => 'CPF do aluno',
            'responsavel.data_nascimento' => 'data de nascimento do responsável',
            'dependentes.*.data_nascimento' => 'data de nascimento do aluno',
            'dependentes.*.serie_id' => 'série do aluno',
        ];
    }
}
