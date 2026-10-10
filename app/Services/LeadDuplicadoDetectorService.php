<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Pessoa;
use Illuminate\Support\Collection;

/**
 * Serviço de detecção de interessados (leads) duplicados.
 *
 * Avalia quatro critérios independentes:
 *  1. Telefone normalizado da pessoa (últimos 10 ou 11 dígitos, ignorando máscaras);
 *  2. CPF da pessoa (somente dígitos numéricos);
 *  3. E-mail normalizado da pessoa (minúsculas e sem espaços);
 *  4. Aluno dependente em comum (nome normalizado e data de nascimento coincidente).
 */
class LeadDuplicadoDetectorService
{
    /**
     * Detecta leads potencialmente duplicados para um dado interessado.
     *
     * @return Collection<int, array{
     *     interessado: Interessado,
     *     motivos: list<string>,
     *     detalhes: list<string>
     * }>
     */
    public function detectar(Interessado $lead): Collection
    {
        $lead->loadMissing(['pessoa', 'dependentes']);

        $pessoa = $lead->pessoa;
        $duplicadosPorId = [];

        // 1. Detecção por Telefone Normalizado
        if ($pessoa && filled($pessoa->telefone)) {
            $this->detectarPorTelefone($lead, $pessoa, $duplicadosPorId);
        }

        // 2. Detecção por CPF
        if ($pessoa && filled($pessoa->cpf)) {
            $this->detectarPorCpf($lead, $pessoa, $duplicadosPorId);
        }

        // 3. Detecção por E-mail Normalizado
        if ($pessoa && filled($pessoa->email)) {
            $this->detectarPorEmail($lead, $pessoa, $duplicadosPorId);
        }

        // 4. Detecção por Aluno Dependente em Comum
        if ($lead->dependentes->isNotEmpty()) {
            $this->detectarPorDependentes($lead, $duplicadosPorId);
        }

        if (empty($duplicadosPorId)) {
            return collect();
        }

        // Carrega as instâncias dos interessados detectados com relações relevantes
        $ids = array_keys($duplicadosPorId);
        $interessados = Interessado::query()
            ->with(['pessoa', 'status', 'usuario', 'dependentes'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $resultado = collect();

        foreach ($duplicadosPorId as $outroId => $info) {
            $outroLead = $interessados->get($outroId);

            if (! $outroLead) {
                continue;
            }

            $resultado->push([
                'interessado' => $outroLead,
                'motivos' => array_values(array_unique($info['motivos'])),
                'detalhes' => array_values(array_unique($info['detalhes'])),
            ]);
        }

        // Ordena por quantidade de motivos coincidentes decrescente, depois pelo ID mais recente
        return $resultado->sort(function (array $a, array $b): int {
            $cmpMotivos = count($b['motivos']) <=> count($a['motivos']);
            if ($cmpMotivos !== 0) {
                return $cmpMotivos;
            }

            return $b['interessado']->id <=> $a['interessado']->id;
        })->values();
    }

    /**
     * Verifica se o lead possui qualquer duplicado em potencial.
     */
    public function temDuplicados(Interessado $lead): bool
    {
        return $this->detectar($lead)->isNotEmpty();
    }

    /**
     * Retorna a contagem de leads duplicados em potencial.
     */
    public function contarDuplicados(Interessado $lead): int
    {
        return $this->detectar($lead)->count();
    }

    /**
     * Retorna apenas os IDs dos leads duplicados.
     *
     * @return list<int>
     */
    public function duplicadosIds(Interessado $lead): array
    {
        return $this->detectar($lead)
            ->pluck('interessado.id')
            ->all();
    }

    /**
     * Detecta duplicados comparando telefone normalizado (últimos 10 ou 11 dígitos).
     *
     * @param  array<int, array{motivos: list<string>, detalhes: list<string>}>  $duplicadosPorId
     */
    private function detectarPorTelefone(Interessado $lead, Pessoa $pessoa, array &$duplicadosPorId): void
    {
        $digitos = preg_replace('/\D/', '', (string) $pessoa->telefone) ?? '';

        if (strlen($digitos) < 10) {
            return;
        }

        // Busca pessoas associadas a outros interessados com o mesmo final de telefone
        $pessoasMesmoTelefone = Pessoa::query()
            ->whereHas('interessado', fn ($q) => $q->where('id', '!=', $lead->id))
            ->comTelefone($digitos)
            ->pluck('id');

        if ($pessoasMesmoTelefone->isEmpty()) {
            return;
        }

        $outrosLeads = Interessado::query()
            ->where('id', '!=', $lead->id)
            ->whereIn('pessoa_id', $pessoasMesmoTelefone)
            ->pluck('id');

        foreach ($outrosLeads as $outroId) {
            $duplicadosPorId[$outroId]['motivos'][] = 'telefone';
            $duplicadosPorId[$outroId]['detalhes'][] = "Telefone coincidente: {$pessoa->telefone}";
        }
    }

    /**
     * Detecta duplicados comparando CPF numérico.
     *
     * @param  array<int, array{motivos: list<string>, detalhes: list<string>}>  $duplicadosPorId
     */
    private function detectarPorCpf(Interessado $lead, Pessoa $pessoa, array &$duplicadosPorId): void
    {
        $cpfLimpo = preg_replace('/\D/', '', (string) $pessoa->cpf) ?? '';

        if (strlen($cpfLimpo) !== 11) {
            return;
        }

        $pessoasMesmoCpf = Pessoa::query()
            ->whereHas('interessado', fn ($q) => $q->where('id', '!=', $lead->id))
            ->where('cpf', $cpfLimpo)
            ->pluck('id');

        if ($pessoasMesmoCpf->isEmpty()) {
            return;
        }

        $outrosLeads = Interessado::query()
            ->where('id', '!=', $lead->id)
            ->whereIn('pessoa_id', $pessoasMesmoCpf)
            ->pluck('id');

        foreach ($outrosLeads as $outroId) {
            $duplicadosPorId[$outroId]['motivos'][] = 'cpf';
            $duplicadosPorId[$outroId]['detalhes'][] = "CPF coincidente: {$pessoa->cpf}";
        }
    }

    /**
     * Detecta duplicados comparando e-mail em caixa baixa e sem espaços.
     *
     * @param  array<int, array{motivos: list<string>, detalhes: list<string>}>  $duplicadosPorId
     */
    private function detectarPorEmail(Interessado $lead, Pessoa $pessoa, array &$duplicadosPorId): void
    {
        $email = mb_strtolower(trim((string) $pessoa->email));

        if ($email === '') {
            return;
        }

        $pessoasMesmoEmail = Pessoa::query()
            ->whereHas('interessado', fn ($q) => $q->where('id', '!=', $lead->id))
            ->whereRaw('TRIM(LOWER(email)) = ?', [$email])
            ->pluck('id');

        if ($pessoasMesmoEmail->isEmpty()) {
            return;
        }

        $outrosLeads = Interessado::query()
            ->where('id', '!=', $lead->id)
            ->whereIn('pessoa_id', $pessoasMesmoEmail)
            ->pluck('id');

        foreach ($outrosLeads as $outroId) {
            $duplicadosPorId[$outroId]['motivos'][] = 'email';
            $duplicadosPorId[$outroId]['detalhes'][] = "E-mail coincidente: {$email}";
        }
    }

    /**
     * Detecta duplicados comparando dependentes vinculados (nome normalizado e data de nascimento).
     *
     * @param  array<int, array{motivos: list<string>, detalhes: list<string>}>  $duplicadosPorId
     */
    private function detectarPorDependentes(Interessado $lead, array &$duplicadosPorId): void
    {
        foreach ($lead->dependentes as $dep) {
            $nomeNorm = InteressadoDependente::nomeNormalizado($dep->nome_crianca);

            if ($nomeNorm === '') {
                continue;
            }

            $query = InteressadoDependente::query()
                ->where('interessado_id', '!=', $lead->id);

            // Se ambos têm data de nascimento, usa o par (nome + nascimento)
            if ($dep->data_nascimento !== null) {
                $query->whereDate('data_nascimento', $dep->data_nascimento);
            }

            $dependentesCandidatos = $query->get();

            foreach ($dependentesCandidatos as $outroDep) {
                if (InteressadoDependente::nomeNormalizado($outroDep->nome_crianca) !== $nomeNorm) {
                    continue;
                }

                // Se o dependente do lead não tem data de nascimento cadastrada,
                // só considera duplicado se o nome for composto (evita falso positivo para nomes simples como "Lucas")
                if ($dep->data_nascimento === null && ! str_contains($nomeNorm, ' ')) {
                    continue;
                }

                $outroLeadId = $outroDep->interessado_id;
                $nascFormatada = $dep->data_nascimento ? ' (Nasc: '.$dep->data_nascimento->format('d/m/Y').')' : '';

                $duplicadosPorId[$outroLeadId]['motivos'][] = 'dependente';
                $duplicadosPorId[$outroLeadId]['detalhes'][] = "Aluno dependente em comum: {$dep->nome_crianca}{$nascFormatada}";
            }
        }
    }
}
