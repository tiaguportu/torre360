<?php

namespace App\Services\Educacenso;

use App\Enums\SituacaoMatricula;
use App\Models\InstituicaoEnsino;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\Unidade;
use Illuminate\Support\Collection;

/**
 * Orquestra a exportação completa do Educacenso (INEP) para uma ou mais
 * Instituições de Ensino, gerando todos os registros obrigatórios:
 *   00 – Identificação da Escola
 *   10 – Caracterização e Infraestrutura
 *   20 – Turmas
 *   30 – Pessoas Físicas
 *   40 – Vínculos de Gestores Escolares
 *   50 – Vínculos de Profissionais Escolares (Docentes)
 *   60 – Vínculos de Alunos
 */
class EducacensoInstituicaoExporter
{
    private EducacensoTurmaExporter $turmaExporter;

    private EducacensoPessoaExporter $pessoaExporter;

    public function __construct()
    {
        $this->turmaExporter = new EducacensoTurmaExporter;
        $this->pessoaExporter = new EducacensoPessoaExporter;
    }

    /**
     * Exporta uma coleção de InstituicaoEnsino no formato completo do Educacenso.
     *
     * @param  Collection<int, InstituicaoEnsino>  $instituicoes
     */
    public function export(Collection $instituicoes): string
    {
        $instituicoes->each(function (InstituicaoEnsino $inst) {
            $inst->loadMissing([
                'units.endereco.cidade.estado',
                'units.cursos.series.turmas.serie.curso.unidade.instituicaoEnsino',
                'units.cursos.series.turmas.etapaEnsino',
                'units.cursos.series.turmas.etapaEnsinoAgregada',
                'units.cursos.series.turmas.horariosFuncionamento',
                'units.representantesLegais',
            ]);
        });

        $lines = [];

        foreach ($instituicoes as $instituicao) {
            foreach ($instituicao->units as $unidade) {
                // Coletar todas as turmas desta unidade
                $turmasUnidade = collect();
                foreach ($unidade->cursos as $curso) {
                    foreach ($curso->series as $serie) {
                        foreach ($serie->turmas as $turma) {
                            $turmasUnidade->push($turma);
                        }
                    }
                }

                // Coletar todos os alunos (pessoas com matrícula ativa nesta unidade)
                $pessoasAlunos = $this->getPessoasAlunos($turmasUnidade);

                // Coletar docentes (professor_conselheiro de cada turma)
                $pessoasDocentes = $this->getPessoasDocentes($turmasUnidade);

                // Coletar gestores da unidade
                $gestores = $unidade->representantesLegais ?? collect();

                // Conjunto único de pessoas para o Registro 30
                $todasPessoas = $pessoasAlunos
                    ->merge($pessoasDocentes)
                    ->merge($gestores)
                    ->unique('id');

                // ---- Registro 00 ----
                $lines[] = $this->buildRegistro00($unidade);

                // ---- Registro 10 ----
                $lines[] = $this->buildRegistro10($unidade);

                // ---- Registro 20 (turmas) ----
                foreach ($turmasUnidade as $turma) {
                    $lines[] = $this->turmaExporter->buildRegistro20Line($turma);
                }

                // ---- Registro 30 (pessoas físicas) ----
                foreach ($todasPessoas as $pessoa) {
                    $lines[] = $this->pessoaExporter->buildRegistro30Line($pessoa);
                }

                // ---- Registro 40 (gestores) ----
                foreach ($gestores as $gestor) {
                    $lines[] = $this->buildRegistro40($unidade, $gestor);
                }

                // ---- Registro 50 (docentes) ----
                foreach ($pessoasDocentes->unique('id') as $docente) {
                    $turmasDocente = $turmasUnidade->filter(
                        fn (Turma $t) => $t->professor_conselheiro_id === $docente->id
                    );
                    $lines[] = $this->buildRegistro50($unidade, $docente, $turmasDocente);
                }

                // ---- Registro 60 (alunos – vínculos) ----
                foreach ($turmasUnidade as $turma) {
                    $matriculasAtivas = $turma->matriculas()
                        ->where('situacao', SituacaoMatricula::ATIVA)
                        ->with('pessoa')
                        ->get();

                    foreach ($matriculasAtivas as $matricula) {
                        if ($matricula->pessoa) {
                            $lines[] = $this->buildRegistro60($unidade, $turma, $matricula);
                        }
                    }
                }
            }
        }

        return implode("\r\n", $lines);
    }

    // =========================================================================
    // Registro 00 – Identificação da Escola
    // Layout: 33 campos separados por |
    // =========================================================================
    private function buildRegistro00(Unidade $unidade): string
    {
        $end = $unidade->endereco;
        $cidade = $end?->cidade;
        $estado = $cidade?->estado;

        // 1. Tipo de registro
        $f1 = '00';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? '');

        // 3. Código da escola no sistema próprio
        $f3 = (string) $unidade->id;

        // 4. Situação de funcionamento (1=Em atividade, 2=Paralisada, 3=Extinta)
        $f4 = $this->mapSituacaoFuncionamento($unidade->situacao_funcionamento ?? '1');

        // 5. Nome da escola
        $f5 = $this->sanitizeString($unidade->nome ?? '', 150);

        // 6. CEP (8 dígitos)
        $f6 = $end ? preg_replace('/[^0-9]/', '', $end->cep ?? '') : '';

        // 7. Tipo de logradouro (não disponível no sistema)
        $f7 = '';

        // 8. Logradouro
        $f8 = $this->sanitizeString($end?->logradouro ?? '', 100);

        // 9. Número
        $f9 = $this->sanitizeString($end?->numero ?? '', 20);

        // 10. Complemento
        $f10 = $this->sanitizeString($end?->complemento ?? '', 50);

        // 11. Bairro
        $f11 = $this->sanitizeString($end?->bairro ?? '', 50);

        // 12. Município (código IBGE de 7 dígitos)
        $f12 = $cidade?->codigo_ibge ?? '';

        // 13. UF (sigla do estado)
        $f13 = $estado?->sigla ?? '';

        // 14. Telefone (apenas dígitos, 10-11 caracteres)
        $f14 = preg_replace('/[^0-9]/', '', $unidade->telefone ?? '');

        // 15. E-mail
        $f15 = ! empty($unidade->email) ? mb_strtolower(trim($unidade->email)) : '';

        // 16. Código do órgão regional de ensino
        $f16 = $this->extractCode($unidade->codigo_orgao_regional_ensino ?? '');

        // 17-33: Campos adicionais (categoria escola privada, convênio, CNPJ, mantenedora, etc.)
        // Enviados como vazios pois não temos esses dados estruturados
        $extras = array_fill(0, 17, '');

        $fields = array_merge(
            [$f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9, $f10, $f11, $f12, $f13, $f14, $f15, $f16],
            $extras
        );

        return implode('|', $fields);
    }

    // =========================================================================
    // Registro 10 – Caracterização e Infraestrutura da Escola
    // Layout: 65 campos separados por |
    // =========================================================================
    private function buildRegistro10(Unidade $unidade): string
    {
        // 1. Tipo de registro
        $f1 = '10';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? '');

        // 3. Localização (zona) – 1=Urbana, 2=Rural
        $f3 = $this->mapLocalizacaoZona($unidade->localizacao_zona ?? '');

        // 4. Localização Diferenciada – 0=Não, 1=Área de assentamento, 2=Terra indígena, 3=Área quilombola
        $f4 = $this->mapLocalizacaoDiferenciada($unidade->localizacao_diferenciada ?? '0');

        // 5. Dependência Administrativa – 1=Federal, 2=Estadual, 3=Municipal, 4=Privada
        $f5 = $this->mapDependenciaAdministrativa($unidade->dependencia_administrativa ?? '4');

        // 6-65: Infraestrutura (abastecimento de água, energia, esgoto, banheiro, laboratório,
        // sala de leitura, quadra, refeitório, internet, etc.)
        // Como não temos esses dados no banco, enviamos tudo vazio (campo não preenchido)
        $infraestrutura = array_fill(0, 60, '');

        $fields = array_merge([$f1, $f2, $f3, $f4, $f5], $infraestrutura);

        return implode('|', $fields);
    }

    // =========================================================================
    // Registro 40 – Gestor Escolar
    // Layout: 12 campos separados por |
    // =========================================================================
    private function buildRegistro40(Unidade $unidade, Pessoa $gestor): string
    {
        // 1. Tipo de registro
        $f1 = '40';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? '');

        // 3. Código da pessoa no sistema
        $f3 = (string) ($gestor->codigo ?? $gestor->id);

        // 4. Código INEP da pessoa
        $f4 = $this->extractCode($gestor->codigo_inep ?? $gestor->id_inep ?? '');

        // 5. CPF
        $f5 = $this->sanitizeCpf($gestor->cpf);

        // 6. Cargo no Educacenso: 1=Diretor, 2=Vice-diretor, 3=Auxiliar/Assistente, 4=Coordenador pedagógico
        $cargo = mb_strtolower($gestor->pivot?->cargo ?? '');
        if (str_contains($cargo, 'vice')) {
            $f6 = '2';
        } elseif (str_contains($cargo, 'diretor') || str_contains($cargo, 'diretora')) {
            $f6 = '1';
        } elseif (str_contains($cargo, 'auxiliar') || str_contains($cargo, 'assistente')) {
            $f6 = '3';
        } elseif (str_contains($cargo, 'coordenador') || str_contains($cargo, 'pedagog')) {
            $f6 = '4';
        } else {
            $f6 = '1'; // Diretor como padrão
        }

        // 7-12: Tipo de vínculo trabalhista, escolaridade, concurso, etc. – não disponível
        $extras = array_fill(0, 6, '');

        $fields = array_merge([$f1, $f2, $f3, $f4, $f5, $f6], $extras);

        return implode('|', $fields);
    }

    // =========================================================================
    // Registro 50 – Profissional Escolar (Docente)
    // Layout: 22 campos separados por |
    // =========================================================================
    private function buildRegistro50(Unidade $unidade, Pessoa $docente, Collection $turmasDocente): string
    {
        // 1. Tipo de registro
        $f1 = '50';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? '');

        // 3. Código da pessoa no sistema
        $f3 = (string) ($docente->codigo ?? $docente->id);

        // 4. Código INEP da pessoa
        $f4 = $this->extractCode($docente->codigo_inep ?? $docente->id_inep ?? '');

        // 5. CPF
        $f5 = $this->sanitizeCpf($docente->cpf);

        // 6-22: Tipo de vínculo trabalhista, escolaridade, formação, etc. – não disponível no banco
        $extras = array_fill(0, 17, '');

        $fields = array_merge([$f1, $f2, $f3, $f4, $f5], $extras);

        return implode('|', $fields);
    }

    // =========================================================================
    // Registro 60 – Vínculo do Aluno com a Turma/Escola
    // Layout: 15 campos separados por |
    // =========================================================================
    private function buildRegistro60(Unidade $unidade, Turma $turma, Matricula $matricula): string
    {
        $pessoa = $matricula->pessoa;

        // 1. Tipo de registro
        $f1 = '60';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? '');

        // 3. Código da pessoa no sistema
        $f3 = (string) ($pessoa->codigo ?? $pessoa->id);

        // 4. Código INEP da pessoa
        $f4 = $this->extractCode($pessoa->codigo_inep ?? $pessoa->id_inep ?? '');

        // 5. CPF da pessoa
        $f5 = $this->sanitizeCpf($pessoa->cpf);

        // 6. Código da turma no sistema
        $f6 = (string) ($turma->codigo ?? $turma->id);

        // 7. Código INEP da turma
        $f7 = $this->extractCode($turma->codigo_inep ?? '');

        // 8. Situação da matrícula
        // 1=Cursando, 2=Aprovado, 3=Reprovado, 4=Transferido, 5=Abandono, 6=Falecido, 8=Deixou de frequentar
        $f8 = $this->mapSituacaoMatricula($matricula->situacao);

        // 9. Código da instituição de destino (transferência) – nulo
        $f9 = '';

        // 10. Dependência administrativa da instituição de destino – nulo
        $f10 = '';

        // 11. Código UF da instituição de destino – nulo
        $f11 = '';

        // 12. Tipo de mediação da turma
        $f12 = (string) ($turma->tipo_mediacao_didatico_pedagogica ?? '1');

        // 13-15: Complementares – vazios
        $extras = array_fill(0, 3, '');

        $fields = array_merge(
            [$f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9, $f10, $f11, $f12],
            $extras
        );

        return implode('|', $fields);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Coleta todas as pessoas (alunos) com matrícula ativa nas turmas informadas.
     *
     * @param  Collection<int, Turma>  $turmas
     * @return Collection<int, Pessoa>
     */
    private function getPessoasAlunos(Collection $turmas): Collection
    {
        $pessoas = collect();

        foreach ($turmas as $turma) {
            $matriculas = $turma->matriculas()
                ->where('situacao', SituacaoMatricula::ATIVA)
                ->with([
                    'pessoa.naturalidade.estado',
                    'pessoa.enderecos.cidade.estado',
                    'pessoa.responsaveis',
                    'pessoa.necessidadesEducacaoEspecial.categoria',
                    'pessoa.transtornosAprendizagem',
                    'pessoa.recursosAcessibilidade',
                    'pessoa.matriculas.turma.serie.curso.unidade.instituicaoEnsino',
                    'pessoa.unidadesRepresentadas.instituicaoEnsino',
                ])
                ->get();

            foreach ($matriculas as $matricula) {
                if ($matricula->pessoa) {
                    $pessoas->push($matricula->pessoa);
                }
            }
        }

        return $pessoas->unique('id');
    }

    /**
     * Coleta todos os docentes (professor_conselheiro) das turmas informadas.
     *
     * @param  Collection<int, Turma>  $turmas
     * @return Collection<int, Pessoa>
     */
    private function getPessoasDocentes(Collection $turmas): Collection
    {
        $ids = $turmas
            ->pluck('professor_conselheiro_id')
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Pessoa::with([
            'naturalidade.estado',
            'enderecos.cidade.estado',
            'responsaveis',
            'necessidadesEducacaoEspecial.categoria',
            'transtornosAprendizagem',
            'recursosAcessibilidade',
            'matriculas.turma.serie.curso.unidade.instituicaoEnsino',
            'unidadesRepresentadas.instituicaoEnsino',
        ])->whereIn('id', $ids)->get();
    }

    /**
     * Extrai o código numérico inicial de uma string "1 - Descrição".
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
     * Sanitiza texto para os padrões do Educacenso (A-Z 0-9 ª º - sem acentos).
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
     * Mapeia a situação de funcionamento para o código INEP.
     * 1=Em atividade, 2=Paralisada, 3=Extinta no ano do censo, 4=Extinta em anos anteriores
     */
    private function mapSituacaoFuncionamento(mixed $valor): string
    {
        $v = mb_strtolower(trim((string) $valor));

        if (in_array($v, ['1', 'ativa', 'em atividade', 'ativo', 'em_atividade'])) {
            return '1';
        }
        if (in_array($v, ['2', 'paralisada', 'paralisado'])) {
            return '2';
        }
        if (in_array($v, ['3', 'extinta', 'extinto', 'encerrada'])) {
            return '3';
        }

        return '1'; // padrão: em atividade
    }

    /**
     * Mapeia localização/zona para o código INEP (1=Urbana, 2=Rural).
     */
    private function mapLocalizacaoZona(mixed $valor): string
    {
        $v = mb_strtolower(trim((string) $valor));

        if (in_array($v, ['1', 'urbana', 'urbano'])) {
            return '1';
        }
        if (in_array($v, ['2', 'rural'])) {
            return '2';
        }

        return '1'; // padrão: urbana
    }

    /**
     * Mapeia localização diferenciada para o código INEP.
     * 0=Não, 1=Área de assentamento, 2=Terra indígena, 3=Área remanescente quilombola
     */
    private function mapLocalizacaoDiferenciada(mixed $valor): string
    {
        $v = mb_strtolower(trim((string) $valor));

        if (in_array($v, ['0', 'nao', 'não', 'no', '', 'nenhuma'])) {
            return '0';
        }
        if (in_array($v, ['1', 'assentamento'])) {
            return '1';
        }
        if (in_array($v, ['2', 'indigena', 'indígena', 'terra indigena', 'terra_indigena'])) {
            return '2';
        }
        if (in_array($v, ['3', 'quilombola'])) {
            return '3';
        }

        return '0';
    }

    /**
     * Mapeia dependência administrativa para o código INEP.
     * 1=Federal, 2=Estadual, 3=Municipal, 4=Privada
     */
    private function mapDependenciaAdministrativa(mixed $valor): string
    {
        $v = mb_strtolower(trim((string) $valor));

        if (in_array($v, ['1', 'federal'])) {
            return '1';
        }
        if (in_array($v, ['2', 'estadual'])) {
            return '2';
        }
        if (in_array($v, ['3', 'municipal'])) {
            return '3';
        }
        if (in_array($v, ['4', 'privada', 'particular', 'privado'])) {
            return '4';
        }

        return '4'; // padrão: privada
    }

    /**
     * Mapeia a situação da matrícula para o código INEP do Registro 60.
     * 1=Cursando, 2=Aprovado, 3=Reprovado, 4=Transferido, 5=Abandono, 6=Falecido, 8=Deixou de frequentar
     */
    private function mapSituacaoMatricula(mixed $situacao): string
    {
        if ($situacao instanceof SituacaoMatricula) {
            return match ($situacao) {
                SituacaoMatricula::ATIVA => '1',
                SituacaoMatricula::CONCLUIDA => '2',
                SituacaoMatricula::CANCELADA => '5',
                SituacaoMatricula::EVASAO => '5',
                SituacaoMatricula::TRANCADA => '8',
                default => '1',
            };
        }

        $v = mb_strtolower(trim((string) $situacao));

        if (in_array($v, ['ativa', 'ativo', 'cursando', '1'])) {
            return '1';
        }
        if (in_array($v, ['concluido', 'concluida', 'aprovado', 'aprovada', '2'])) {
            return '2';
        }
        if (in_array($v, ['reprovado', 'reprovada', '3'])) {
            return '3';
        }
        if (in_array($v, ['transferido', 'transferida', '4'])) {
            return '4';
        }
        if (in_array($v, ['evasao', 'evasão', 'abandono', 'cancelada', 'cancelado', '5'])) {
            return '5';
        }
        if (in_array($v, ['trancada', 'trancado', '8'])) {
            return '8';
        }

        return '1';
    }
}
