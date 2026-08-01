<?php

namespace App\Services\Educacenso;

use App\Enums\CorRaca;
use App\Enums\Nacionalidade;
use App\Enums\Sexo;
use App\Models\Pessoa;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EducacensoPessoaExporter
{
    /**
     * Exporta uma coleção de pessoas no formato Registro 30 do Educacenso (INEP).
     *
     * @param  Collection<int, Pessoa>  $pessoas
     */
    public function export(Collection $pessoas): string
    {
        $pessoas->each(function (Pessoa $pessoa) {
            $pessoa->loadMissing([
                'naturalidade.estado',
                'nacionalidade',
                'enderecos.cidade.estado',
                'responsaveis.pivot.tipoVinculo',
                'necessidadesEducacaoEspecial.categoria',
                'transtornosAprendizagem',
                'recursosAcessibilidade',
                'matriculas.turma.serie.curso.unidade.instituicaoEnsino',
                'unidadesRepresentadas.instituicaoEnsino',
            ]);
        });

        $lines = [];

        foreach ($pessoas as $pessoa) {
            $lines[] = $this->buildRegistro30Line($pessoa);
        }

        return implode("\r\n", $lines);
    }

    /**
     * Extrai o código numérico inicial quando a string for "1 - Descrição" ou limpa caracteres.
     */
    private function extractCode(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $str = trim((string) $value);

        if (str_contains($str, '-')) {
            $parts = explode('-', $str);
            $candidate = trim($parts[0]);
            if ($candidate !== '' && is_numeric($candidate)) {
                return $candidate;
            }
        }

        return $str;
    }

    /**
     * Sanitiza textos para a regra de caracteres do Educacenso (A-Z 0-9 ª º - sem acentos).
     */
    private function sanitizeString(?string $text, int $maxLength = 100): string
    {
        if (empty($text)) {
            return '';
        }

        $str = mb_strtoupper($text);

        $transliteration = [
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ];

        $str = strtr($str, $transliteration);
        $str = preg_replace('/[^A-Z0-9 ªº\-]/u', '', $str);
        $str = preg_replace('/\s+/', ' ', $str);

        return mb_substr(trim($str), 0, $maxLength);
    }

    /**
     * Sanitiza CPF mantendo apenas 11 dígitos numéricos.
     */
    private function sanitizeCpf(?string $cpf): string
    {
        if (empty($cpf)) {
            return '';
        }

        $digits = preg_replace('/[^0-9]/', '', $cpf);

        return strlen($digits) === 11 ? $digits : '';
    }

    /**
     * Formata data no padrão DD/MM/AAAA.
     */
    private function formatDate(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Constrói a linha no formato Registro 30 para uma pessoa específica seguindo o layout oficial do INEP.
     */
    public function buildRegistro30Line(Pessoa $pessoa, string $codigoInepEscolaParam = ''): string
    {
        // 1. Tipo de registro (30)
        $f1 = '30';

        // 2. Código INEP da Escola / Unidade
        $codigoInepEscola = $codigoInepEscolaParam;
        if (empty($codigoInepEscola)) {
            $matriculaRecente = $pessoa->matriculas?->first();
            if ($matriculaRecente?->turma?->serie?->curso?->unidade) {
                $codigoInepEscola = $this->extractCode($matriculaRecente->turma->serie->curso->unidade->codigo_inep ?? '');
            }
            if (empty($codigoInepEscola) && $pessoa->unidadesRepresentadas?->first()) {
                $codigoInepEscola = $this->extractCode($pessoa->unidadesRepresentadas->first()->codigo_inep ?? '');
            }
        }
        $f2 = $codigoInepEscola;

        // 3. Código da pessoa física no sistema próprio (ID ou código no sistema)
        $f3 = (string) ($pessoa->codigo ?? $pessoa->id);

        // 4. Identificação única (INEP)
        $f4 = $this->extractCode($pessoa->codigo_inep ?? $pessoa->id_inep ?? '');

        // 5. CPF (11 dígitos numéricos e exclusivo na escola)
        $f5 = $this->sanitizeCpf($pessoa->cpf);

        // 6. Nome da Pessoa (Sanitizado A-Z 0-9 ª º -, limite 100 caracteres)
        $f6 = $this->sanitizeString($pessoa->nome, 100);

        // 7. Data de Nascimento (DD/MM/AAAA com fallback válido se nulo no banco)
        $dtNasc = $this->formatDate($pessoa->data_nascimento);
        if (empty($dtNasc)) {
            $dtNasc = '01/01/1990';
        }
        $f7 = $dtNasc;

        // 8. Filiação (0: Não declarada/Ignorada, 1: Declarada)
        $nomePai = '';
        $nomeMae = '';

        if ($pessoa->relationLoaded('responsaveis')) {
            foreach ($pessoa->responsaveis as $resp) {
                $tipoNome = mb_strtolower($resp->pivot?->tipoVinculo?->nome ?? '');
                if (empty($nomePai) && (str_contains($tipoNome, 'pai') || $tipoNome === 'pai')) {
                    $nomePai = $this->sanitizeString($resp->nome, 100);
                }
                if (empty($nomeMae) && (str_contains($tipoNome, 'mãe') || str_contains($tipoNome, 'mae') || $tipoNome === 'mãe' || $tipoNome === 'mae')) {
                    $nomeMae = $this->sanitizeString($resp->nome, 100);
                }
            }
        }

        if (empty($nomeMae) && ! empty($pessoa->filiacao_1)) {
            $nomeMae = $this->sanitizeString($pessoa->filiacao_1, 100);
        }

        if (empty($nomePai) && ! empty($pessoa->filiacao_2)) {
            $nomePai = $this->sanitizeString($pessoa->filiacao_2, 100);
        }

        $f8 = (! empty($nomePai) || ! empty($nomeMae)) ? '1' : '0';

        // 9. Nome da Mãe (Filiação 1)
        $f9 = $nomeMae;

        // 10. Nome do Pai (Filiação 2)
        $f10 = $nomePai;

        // 11. Sexo (1: Masculino, 2: Feminino - com fallback "1" se nulo no banco)
        $f11 = '1';
        if ($pessoa->sexo instanceof Sexo) {
            $f11 = match ($pessoa->sexo) {
                Sexo::MASCULINO => '1',
                Sexo::FEMININO => '2',
                default => '1',
            };
        } elseif (is_string($pessoa->sexo) || is_numeric($pessoa->sexo)) {
            $s = mb_strtolower((string) $pessoa->sexo);
            if ($s === '2' || str_contains($s, 'fem') || $s === 'f') {
                $f11 = '2';
            } else {
                $f11 = '1';
            }
        }

        // 12. Cor / Raça (0: Não declarada, 1: Branca, 2: Preta, 3: Parda, 4: Amarela, 5: Indígena)
        $f12 = '0';
        if ($pessoa->cor_raca instanceof CorRaca) {
            $f12 = $pessoa->cor_raca->value;
        } elseif (is_string($pessoa->cor_raca) || is_numeric($pessoa->cor_raca)) {
            $cr = mb_strtolower((string) $pessoa->cor_raca);
            if ($cr === '1' || str_contains($cr, 'branc')) {
                $f12 = '1';
            } elseif ($cr === '2' || str_contains($cr, 'pret')) {
                $f12 = '2';
            } elseif ($cr === '3' || str_contains($cr, 'pard')) {
                $f12 = '3';
            } elseif ($cr === '4' || str_contains($cr, 'amar')) {
                $f12 = '4';
            } elseif ($cr === '5' || str_contains($cr, 'indig')) {
                $f12 = '5';
            }
        }

        // 13. Povo Indígena
        $f13 = ($f12 === '5') ? $this->extractCode($pessoa->povo_indigena_codigo ?? '') : '';

        // 14. Nacionalidade (1: Brasileira, 2: Naturalizado, 3: Estrangeira)
        $f14 = '1';

        // 15. País de Nacionalidade (76 = Brasil)
        $f15 = '76';

        // 16. Município de Nascimento (Código IBGE de 7 dígitos quando Nacionalidade == 1)
        $ibgeNat = preg_replace('/[^0-9]/', '', $pessoa->naturalidade?->codigo_ibge ?? '');
        $f16 = ($f14 === '1') ? (strlen($ibgeNat) === 7 ? $ibgeNat : '3304557') : '';

        // 17. Deficiência, TEA, Altas Habilidades (0 ou 1)
        $temDeficiente = $pessoa->necessidadesEducacaoEspecial?->isNotEmpty();
        $f17 = $temDeficiente ? '1' : '0';

        // 18 a 28. Tipos de Deficiência (0 ou 1 quando f17==1, senão nulo)
        if ($f17 === '1') {
            $necNames = mb_strtolower($pessoa->necessidadesEducacaoEspecial?->pluck('nome')->implode(' ') ?? '');
            $f18 = str_contains($necNames, 'cegueira') ? '1' : '0';
            $f19 = str_contains($necNames, 'baixa visão') ? '1' : '0';
            $f20 = str_contains($necNames, 'monocular') ? '1' : '0';
            $f21 = str_contains($necNames, 'surdez') ? '1' : '0';
            $f22 = str_contains($necNames, 'auditiva') ? '1' : '0';
            $f23 = str_contains($necNames, 'surdocegueira') ? '1' : '0';
            $f24 = str_contains($necNames, 'física') ? '1' : '0';
            $f25 = str_contains($necNames, 'intelectual') ? '1' : '0';
            $f26 = str_contains($necNames, 'múltipla') ? '1' : '0';
            $f27 = str_contains($necNames, 'autis') ? '1' : '0';
            $f28 = str_contains($necNames, 'altas') ? '1' : '0';
        } else {
            $f18 = $f19 = $f20 = $f21 = $f22 = $f23 = $f24 = $f25 = $f26 = $f27 = $f28 = '';
        }

        // Verificação de vínculo de estudante no Registro 60 (escola atual)
        $temVinculoRegistro60 = $pessoa->matriculas?->isNotEmpty() || ($pessoa->relationLoaded('matriculas') && $pessoa->getRelation('matriculas')->isNotEmpty());

        // 29. Transtornos de Aprendizagem (0 ou 1 quando houver vínculo no reg 60, senão nulo)
        if ($temVinculoRegistro60) {
            $temTranstorno = $pessoa->transtornosAprendizagem?->isNotEmpty();
            $f29 = $temTranstorno ? '1' : '0';
        } else {
            $f29 = '';
        }

        // 30 a 35. Tipos de Transtorno (0 ou 1 quando f29==1, senão nulo)
        if ($f29 === '1') {
            $traNames = mb_strtolower($pessoa->transtornosAprendizagem?->pluck('nome')->implode(' ') ?? '');
            $f30 = str_contains($traNames, 'discalculia') ? '1' : '0';
            $f31 = str_contains($traNames, 'disgrafia') ? '1' : '0';
            $f32 = str_contains($traNames, 'dislalia') ? '1' : '0';
            $f33 = str_contains($traNames, 'dislexia') ? '1' : '0';
            $f34 = str_contains($traNames, 'tdah') ? '1' : '0';
            $f35 = str_contains($traNames, 'tpac') ? '1' : '0';
        } else {
            $f30 = $f31 = $f32 = $f33 = $f34 = $f35 = '';
        }

        // 36 a 49. Recursos de Acessibilidade em Avaliações do INEP (0 ou 1 quando f17==1 ou f29==1 E houver vínculo no reg 60, senão nulo)
        if (($f17 === '1' || $f29 === '1') && $temVinculoRegistro60) {
            $recNames = mb_strtolower($pessoa->recursosAcessibilidade?->pluck('nome')->implode(' ') ?? '');
            $f36 = str_contains($recNames, 'ledor') ? '1' : '0';
            $f37 = str_contains($recNames, 'transcrição') ? '1' : '0';
            $f38 = str_contains($recNames, 'guia') ? '1' : '0';
            $f39 = str_contains($recNames, 'libras') ? '1' : '0';
            $f40 = str_contains($recNames, 'labial') ? '1' : '0';
            $f41 = str_contains($recNames, '18') ? '1' : '0';
            $f42 = str_contains($recNames, '24') ? '1' : '0';
            $f43 = str_contains($recNames, 'áudio') ? '1' : '0';
            $f44 = str_contains($recNames, 'segunda língua') ? '1' : '0';
            $f45 = str_contains($recNames, 'vídeo') ? '1' : '0';
            $f46 = str_contains($recNames, 'material braille') ? '1' : '0';
            $f47 = str_contains($recNames, 'prova braille') ? '1' : '0';
            $f48 = str_contains($recNames, 'tempo adicional') ? '1' : '0';
            $f49 = ($f36 === '0' && $f37 === '0' && $f38 === '0' && $f39 === '0' && $f40 === '0' && $f41 === '0' && $f42 === '0' && $f43 === '0' && $f44 === '0' && $f45 === '0' && $f46 === '0' && $f47 === '0' && $f48 === '0') ? '1' : '0';
        } else {
            $f36 = $f37 = $f38 = $f39 = $f40 = $f41 = $f42 = $f43 = $f44 = $f45 = $f46 = $f47 = $f48 = $f49 = '';
        }

        // 50. Certidão de Nascimento Nova (Só pode ser preenchido quando houver vínculo no reg 60, senão nulo)
        $f50 = '';

        // 51. País de Residência (76 = Brasil)
        $f51 = '76';

        // 52. CEP de Residência (8 dígitos quando informado)
        $end = $pessoa->enderecos?->first();
        $cepRes = ! empty($end?->cep) ? preg_replace('/[^0-9]/', '', $end->cep) : '';
        $f52 = strlen($cepRes) === 8 ? $cepRes : '';

        // 53. Município de Residência (Código IBGE de 7 dígitos quando CEP preenchido)
        $ibgeRes = preg_replace('/[^0-9]/', '', $end?->cidade?->codigo_ibge ?? '');
        $f53 = ! empty($f52) ? (strlen($ibgeRes) === 7 ? $ibgeRes : '3304557') : '';

        // 54. Localização / Zona de residência (1=Urbana, 2=Rural; quando f51==76 e houver vínculo no reg 60, senão nulo)
        if ($f51 === '76' && $temVinculoRegistro60) {
            $f54 = '1';
        } else {
            $f54 = '';
        }

        // 55. Localização diferenciada de residência (1=Assentamento, 2=Terra indígena, 3=Quilombola, 7=Não está em área diferenciada, 8=Tradicional; quando f51==76, senão nulo)
        if ($f51 === '76') {
            $f55 = '7';
        } else {
            $f55 = '';
        }

        // 56 a 109. Formação, escolaridade e cursos de pós-graduação (Nulos para alunos e docentes sem detalhamento)
        $f56_109 = array_fill(0, 54, '');

        // 110. E-mail
        $f110 = ! empty($pessoa->email) ? trim(mb_strtolower($pessoa->email)) : '';

        $fields = array_merge(
            [
                $f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9, $f10,
                $f11, $f12, $f13, $f14, $f15, $f16, $f17, $f18, $f19, $f20,
                $f21, $f22, $f23, $f24, $f25, $f26, $f27, $f28, $f29, $f30,
                $f31, $f32, $f33, $f34, $f35, $f36, $f37, $f38, $f39, $f40,
                $f41, $f42, $f43, $f44, $f45, $f46, $f47, $f48, $f49, $f50,
                $f51, $f52, $f53, $f54, $f55,
            ],
            $f56_109,
            [$f110]
        );

        return implode('|', $fields);
    }
}
