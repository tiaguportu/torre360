<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\Sexo;
use App\Models\Interessado;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SalvarDadosPreAdmissaoRequest extends FormRequest
{
    protected ?Interessado $interessado = null;

    public function authorize(): bool
    {
        $interessado = $this->getInteressado();

        if ($interessado === null) {
            return false;
        }

        if ($interessado->isEtapaFamiliaConcluida()) {
            return false;
        }

        return true;
    }

    protected function failedAuthorization(): void
    {
        $interessado = $this->getInteressado();

        if ($interessado && $interessado->isEtapaFamiliaConcluida()) {
            $token = (string) $this->route('token');
            throw new HttpResponseException(
                redirect()->route('candidato.documentos.show', [
                    'token' => $token,
                    'aba' => 'documentos',
                ])->with('aviso', 'Seus dados cadastrais já foram enviados e estão em análise pela Secretaria Escolar.')
            );
        }

        parent::failedAuthorization();
    }

    public function getInteressado(): ?Interessado
    {
        if ($this->interessado === null) {
            $token = (string) $this->route('token');
            // Só o token do portal em vigor: o convite legado não grava dados (apenas redireciona) e links expirados
            // ou revogados caem aqui como "não autorizado".
            $this->interessado = Interessado::comTokenDocumentosValido($token)->first();
        }

        return $this->interessado;
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareForValidation(): void
    {
        $dados = $this->all();

        if (! empty($dados['responsavel']['data_nascimento'])) {
            $dados['responsavel']['data_nascimento'] = $this->normalizarDataParaIso($dados['responsavel']['data_nascimento']);
        }

        if (! empty($dados['dependentes']) && is_array($dados['dependentes'])) {
            foreach ($dados['dependentes'] as $k => $dep) {
                if (! empty($dep['data_nascimento'])) {
                    $dados['dependentes'][$k]['data_nascimento'] = $this->normalizarDataParaIso($dep['data_nascimento']);
                }
            }
        }

        $this->replace($dados);
    }

    private function normalizarDataParaIso(?string $valor): ?string
    {
        if (! $valor) {
            return null;
        }

        $trimmed = trim($valor);

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $trimmed, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $trimmed;
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
            'responsavel.cidade' => ['nullable', 'string', 'max:100'],
            'responsavel.uf' => ['nullable', 'string', 'max:2'],
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

            'lgpd_aceite' => ['sometimes', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lgpd_aceite.accepted' => 'É necessário concordar com os termos de tratamento de dados (LGPD) para prosseguir.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'responsavel.cpf' => 'CPF do responsável',
            'responsavel.data_nascimento' => 'data de nascimento do responsável',
            'segundo_responsavel.cpf' => 'CPF do segundo responsável',
            'dependentes.*.cpf' => 'CPF do aluno',
            'dependentes.*.data_nascimento' => 'data de nascimento do aluno',
            'dependentes.*.serie_id' => 'série pretendida',
        ];
    }
}
