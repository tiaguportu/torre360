<?php

namespace App\Services;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use Illuminate\Support\Str;

/**
 * Convite de matrícula online: um link único e temporário enviado a um lead já
 * qualificado pelo CRM, para que a própria família confirme/complete os dados
 * (telefone, e-mail, série e turno de cada dependente) sem precisar navegar pelo
 * formulário público completo nem ver dados de qualquer outro registro.
 *
 * A confirmação pelo link NÃO efetiva a matrícula sozinha — quem efetiva continua sendo
 * a secretaria, pela ação "Matricular" já existente (abre o Assistente de Matrícula
 * pré-preenchido via `InteressadoMatriculaService::dadosParaWizard()`). O convite só
 * resolve a coleta de dados, reduzindo a ida e volta por telefone.
 */
class ConviteMatriculaService
{
    private const DIAS_VALIDADE_PADRAO = 7;

    /**
     * Gera (ou renova) o token de convite do lead e retorna a URL pública completa.
     */
    public function gerarConvite(Interessado $interessado, int $diasValidade = self::DIAS_VALIDADE_PADRAO): string
    {
        do {
            $token = Str::random(48);
        } while (Interessado::where('token_convite', $token)->exists());

        $interessado->update([
            'token_convite' => $token,
            'token_convite_expira_em' => now()->addDays($diasValidade),
            'token_convite_usado_em' => null,
        ]);

        return route('captacao.interessado.convite', ['token' => $token]);
    }

    /**
     * Resolve um token para o Interessado correspondente, só se o convite ainda for
     * válido (existe, não expirou, ainda não foi usado).
     */
    public function validarToken(string $token): ?Interessado
    {
        $interessado = Interessado::where('token_convite', $token)->first();

        if (! $interessado || ! $interessado->conviteValido()) {
            return null;
        }

        return $interessado;
    }

    /**
     * Aplica os dados confirmados pela família: atualiza o contato do responsável e a
     * série de cada dependente informado (o turno de preferência, assim como no
     * formulário público original, não é uma coluna estruturada — vai para o relato do
     * histórico de contato), marca o convite como usado e recalcula o lead score (o
     * preenchimento é, em si, um sinal de engajamento).
     *
     * @param  array{telefone?: ?string, email?: ?string}  $dadosResponsavel
     * @param  array<int, array{id: int, serie_id?: ?int, turno_preferencia?: ?string}>  $dependentes  indexado por InteressadoDependente::id
     */
    public function confirmar(Interessado $interessado, array $dadosResponsavel, array $dependentes): void
    {
        if ($interessado->pessoa && ($dadosResponsavel['telefone'] ?? null)) {
            $interessado->pessoa->update(['telefone' => $dadosResponsavel['telefone']]);
        }

        if ($interessado->pessoa && ($dadosResponsavel['email'] ?? null)) {
            $interessado->pessoa->update(['email' => $dadosResponsavel['email']]);
        }

        $dependentesDoInteressado = $interessado->dependentes()->get()->keyBy('id');
        $relatoLinhas = [];

        foreach ($dependentes as $dadosDependente) {
            $dependenteId = $dadosDependente['id'] ?? null;
            $dependente = $dependenteId ? $dependentesDoInteressado->get($dependenteId) : null;

            // Nunca atualiza um dependente que não pertença a este interessado — é
            // exatamente o que impede o convite de um lead de alterar dados de outro.
            if (! $dependente) {
                continue;
            }

            if (! empty($dadosDependente['serie_id'])) {
                $dependente->update(['serie_id' => $dadosDependente['serie_id']]);
            }

            $linha = $dependente->nome_crianca;
            if (! empty($dadosDependente['turno_preferencia'])) {
                $linha .= ' | Turno: '.$dadosDependente['turno_preferencia'];
            }
            $relatoLinhas[] = $linha;
        }

        $interessado->update(['token_convite_usado_em' => now()]);

        $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'Confirmação via Convite Online']);

        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => "A família confirmou os dados de matrícula pelo link de convite online.\n".implode("\n", $relatoLinhas),
            'data_contato' => now(),
        ]);

        LeadScoreService::recalcular($interessado);
    }
}
