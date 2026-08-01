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
    // Layout Oficial Educacenso 2026: EXATAMENTE 53 campos separados por |
    // =========================================================================
    private function buildRegistro00(Unidade $unidade): string
    {
        $end = $unidade->endereco;
        $cidade = $end?->cidade;
        $estado = $cidade?->estado;
        $instituicao = $unidade->instituicaoEnsino;

        // 1. Tipo de registro
        $f1 = '00';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? $instituicao?->codigo_inep ?? '');

        // 3. Situação de funcionamento (1=Em atividade, 2=Paralisada, 3=Extinta)
        $f3 = $this->mapSituacaoFuncionamento($unidade->situacao_funcionamento ?? '1');

        // 4. Data de início do ano letivo (DD/MM/AAAA)
        $f4 = '';

        // 5. Data de término do ano letivo (DD/MM/AAAA)
        $f5 = '';

        // 6. Nome da escola (Sanitizado A-Z 0-9 ª º -, limite 100 caracteres)
        $f6 = $this->sanitizeString($unidade->nome ?? $instituicao?->nome ?? '', 100);

        // 7. CEP (8 dígitos numéricos)
        $cepDigits = $end ? preg_replace('/[^0-9]/', '', $end->cep ?? '') : '';
        $f7 = strlen($cepDigits) === 8 ? $cepDigits : '';

        // 8. Município (código IBGE de 7 dígitos)
        $f8 = $cidade?->codigo_ibge ?? '';

        // 9. Distrito (código de 2 dígitos, "05" para Distrito-Sede se município informado)
        $f9 = ! empty($f8) ? '05' : '';

        // 10. Endereço (logradouro, limite 100 caracteres)
        $f10 = $this->sanitizeString($end?->logradouro ?? '', 100);

        // 11. Número
        $f11 = $this->sanitizeString($end?->numero ?? 'S/N', 100);

        // 12. Complemento
        $f12 = $this->sanitizeString($end?->complemento ?? '', 20);

        // 13. Bairro
        $f13 = $this->sanitizeString($end?->bairro ?? '', 50);

        // Extração de DDD e Telefones
        $telBruto = preg_replace('/[^0-9]/', '', $unidade->telefone ?? $unidade->celular_whatsapp ?? '');
        $ddd = '';
        $telefone = '';

        if (strlen($telBruto) >= 10) {
            $ddd = substr($telBruto, 0, 2);
            $telefone = substr($telBruto, 2, 9);
        }

        // 14. DDD
        $f14 = $ddd;

        // 15. Telefone (até 9 dígitos)
        $f15 = $telefone;

        // 16. Outro telefone de contato
        $f16 = '';

        // 17. E-mail da escola
        $f17 = ! empty($unidade->email) ? mb_strtolower(trim($unidade->email)) : '';

        // 18. Código do órgão regional de ensino
        $f18 = $this->extractCode($unidade->codigo_orgao_regional_ensino ?? '');

        // 19. Localização / Zona da escola (1=Urbana, 2=Rural)
        $f19 = $this->mapLocalizacaoZona($unidade->localizacao_zona ?? '1');

        // 20. Localização diferenciada da escola (0=Não, 1=Assentamento, 2=Terra indígena, 3=Quilombola, 7=Não está, 8=Tradicional)
        $f20 = $this->mapLocalizacaoDiferenciada($unidade->localizacao_diferenciada ?? '0');

        // 21. Dependência administrativa (1=Federal, 2=Estadual, 3=Municipal, 4=Privada)
        $f21 = $this->mapDependenciaAdministrativa($unidade->dependencia_administrativa ?? '4');

        // Regras para campos de Mantenedor / Órgão Vinculado (22 a 32):
        if (in_array($f21, ['1', '2', '3'])) {
            // Pública
            $f22 = $instituicao?->flag_secretaria_educacao_mec ? '1' : '1';
            $f23 = $instituicao?->flag_seguranca_publica_forcas_armadas ? '1' : '0';
            $f24 = $instituicao?->flag_secretaria_saude ? '1' : '0';
            $f25 = $instituicao?->flag_outro_orgao_publico ? '1' : '0';
            $f26 = $f27 = $f28 = $f29 = $f30 = $f31 = $f32 = '';
        } else {
            // Privada (f21 == '4')
            $f22 = $f23 = $f24 = $f25 = '';
            $f26 = '1'; // Empresa / setor privado
            $f27 = '0'; // Sindicatos/cooperativas
            $f28 = '0'; // ONG
            $f29 = '0'; // Sem fins lucrativos
            $f30 = '0'; // Sistema S
            $f31 = '0'; // OSCIP
            $f32 = '1'; // Categoria: 1=Particular
        }

        // Parcerias e Convênios com Poder Público (33 a 46) -> Nulos quando não se aplicam
        $f33 = $f34 = $f35 = $f36 = $f37 = $f38 = $f39 = $f40 = $f41 = $f42 = $f43 = $f44 = $f45 = $f46 = '';

        // CNPJs (47 e 48)
        $cnpjMantenedora = preg_replace('/[^0-9]/', '', $instituicao?->cnpj ?? '');
        $cnpjEscola = preg_replace('/[^0-9]/', '', $unidade->cnpj ?? $cnpjMantenedora);

        $f47 = ($f21 === '4' && strlen($cnpjMantenedora) === 14) ? $cnpjMantenedora : '';
        $f48 = ($f21 === '4' && strlen($cnpjEscola) === 14) ? $cnpjEscola : '';

        // Regulamentação / Autorização no Conselho (49 e 50)
        $f49 = '1'; // 1=Sim, regulamentada/autorizada
        $f50 = $f21 === '3' ? '3' : '2'; // 2=Estadual, 3=Municipal

        // Unidade vinculada, escola sede, IES (51 a 53)
        $f51 = '0'; // 0=Não é unidade vinculada
        $f52 = '';  // Código Escola Sede
        $f53 = '';  // Código IES

        $fields = [
            $f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9, $f10,
            $f11, $f12, $f13, $f14, $f15, $f16, $f17, $f18, $f19, $f20,
            $f21, $f22, $f23, $f24, $f25, $f26, $f27, $f28, $f29, $f30,
            $f31, $f32, $f33, $f34, $f35, $f36, $f37, $f38, $f39, $f40,
            $f41, $f42, $f43, $f44, $f45, $f46, $f47, $f48, $f49, $f50,
            $f51, $f52, $f53,
        ];

        return implode('|', $fields);
    }

    // =========================================================================
    // Registro 10 – Caracterização e Infraestrutura da Escola
    // Layout Oficial Educacenso 2026: EXATAMENTE 187 campos separados por |
    // =========================================================================
    private function buildRegistro10(Unidade $unidade): string
    {
        $instituicao = $unidade->instituicaoEnsino;

        // 1. Tipo de registro
        $f1 = '10';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? $instituicao?->codigo_inep ?? '');

        // 3. Prédio escolar (1=Próprio, 2=Alugado, 3=Cedido, etc.)
        $f3 = '1';

        // 4. Sala em outra escola (0=Não)
        $f4 = '0';

        // 5. Galpão/rancho/barracão (0=Não)
        $f5 = '0';

        // 6. Unidade de atendimento socioeducativo (0=Não)
        $f6 = '0';

        // 7. Unidade prisional (0=Não)
        $f7 = '0';

        // 8. Outros (0=Não)
        $f8 = '0';

        // 9. Forma de ocupação do prédio (1=Próprio, 2=Alugado, 3=Cedido)
        $f9 = '1';

        // 10. Prédio escolar compartilhado com outra escola (0=Não)
        $f10 = '0';

        // 11 a 16. Códigos das escolas compartilhadas 1 a 6 (nulos)
        $f11 = $f12 = $f13 = $f14 = $f15 = $f16 = '';

        // 17. Água potável (1=Sim)
        $f17 = '1';

        // 18 a 21. Abastecimento de água (Rede pública, Poço, Cisterna, Fonte/Rio)
        $f18 = '1'; // Rede pública
        $f19 = $f20 = $f21 = '0';

        // 22 a 25. Energia elétrica (Rede pública, Gerador, Fotovoltaica, Sem energia)
        $f22 = '1'; // Rede pública
        $f23 = $f24 = $f25 = '0';

        // 26 a 29. Esgoto sanitário (Rede pública, Fossa séptica, Fossa rudimentar, Sem esgoto)
        $f26 = '1'; // Rede pública
        $f27 = $f28 = $f29 = '0';

        // 30 a 33. Destinação do lixo (Coleta periódica, Queima, Descarta em área pública, Outra)
        $f30 = '1'; // Coleta periódica
        $f31 = $f32 = $f33 = '0';

        // 34 a 37. Tratamento do lixo / Reciclagem (Separação do lixo, Reaproveitamento, Destinação pós-consumo, Sem tratamento)
        $f34 = '1'; // Separação do lixo
        $f35 = $f36 = '0';
        $f37 = '0';

        // 38 a 187: Dependências, instalações, equipamentos, recursos e órgãos colegiados da escola
        // Preenchemos com 1 para itens essenciais existentes (Salas de aula, diretoria, sala de professores, internet, banheiros)
        // e 0 ou nulo para os demais condicionais, completando rigorosamente os 187 campos.
        $extras = array_fill(0, 150, '0');

        // Mapeamentos específicos nos campos de 38 a 187:
        $extras[0] = '1';  // Campo 38: Almoxarifado / Depósito
        $extras[3] = '1';  // Campo 41: Banheiro
        $extras[4] = '1';  // Campo 42: Banheiro acessível
        $extras[12] = '1'; // Campo 50: Cozinha
        $extras[15] = '1'; // Campo 53: Diretoria / Sala do Diretor
        $extras[30] = '1'; // Campo 68: Sala de professores
        $extras[32] = '1'; // Campo 70: Sala de secretaria
        $extras[34] = '1'; // Campo 72: Salas de aula (utilizadas)

        $fields = array_merge([
            $f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9, $f10,
            $f11, $f12, $f13, $f14, $f15, $f16, $f17, $f18, $f19, $f20,
            $f21, $f22, $f23, $f24, $f25, $f26, $f27, $f28, $f29, $f30,
            $f31, $f32, $f33, $f34, $f35, $f36, $f37,
        ], $extras);

        return implode('|', $fields);
    }

    // =========================================================================
    // Registro 40 – Gestor Escolar
    // Layout Oficial Educacenso 2026: EXATAMENTE 7 campos separados por |
    // =========================================================================
    private function buildRegistro40(Unidade $unidade, Pessoa $gestor): string
    {
        $instituicao = $unidade->instituicaoEnsino;

        // 1. Tipo de registro
        $f1 = '40';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? $instituicao?->codigo_inep ?? '');

        // 3. Código da pessoa no sistema
        $f3 = (string) ($gestor->codigo ?? $gestor->id);

        // 4. Identificação única (INEP) da pessoa
        $f4 = $this->extractCode($gestor->codigo_inep ?? $gestor->id_inep ?? '');

        // 5. Cargo do gestor no Educacenso (1=Diretor, 2=Vice-diretor, 3=Auxiliar/Assistente, 4=Coordenador pedagógico)
        $cargo = mb_strtolower($gestor->pivot?->cargo ?? '');
        if (str_contains($cargo, 'vice')) {
            $f5 = '2';
        } elseif (str_contains($cargo, 'diretor') || str_contains($cargo, 'diretora')) {
            $f5 = '1';
        } elseif (str_contains($cargo, 'auxiliar') || str_contains($cargo, 'assistente')) {
            $f5 = '3';
        } elseif (str_contains($cargo, 'coordenador') || str_contains($cargo, 'pedagog')) {
            $f5 = '4';
        } else {
            $f5 = '1'; // Diretor como padrão
        }

        // 6. Critério de acesso ao cargo/função (1=Concurso público, 2=Eleição, 3=Indicação, 4=Processo seletivo)
        $f6 = '3'; // Indicação como padrão

        // 7. Situação funcional / Regime de contratação / Tipo de vínculo (1=Concursado/Efetivo, 2=Contrato temporário, 3=CLT/Privado)
        $depAdmin = $this->mapDependenciaAdministrativa($unidade->dependencia_administrativa ?? '4');
        $f7 = $depAdmin === '4' ? '3' : '1';

        $fields = [$f1, $f2, $f3, $f4, $f5, $f6, $f7];

        return implode('|', $fields);
    }

    // =========================================================================
    // Registro 50 – Profissional Escolar (Docente)
    // Layout Oficial Educacenso 2026: EXATAMENTE 38 campos separados por |
    // =========================================================================
    private function buildRegistro50(Unidade $unidade, Pessoa $docente, Collection $turmasDocente): string
    {
        $instituicao = $unidade->instituicaoEnsino;
        $firstTurma = $turmasDocente->first();

        // 1. Tipo de registro
        $f1 = '50';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? $instituicao?->codigo_inep ?? '');

        // 3. Código da pessoa no sistema
        $f3 = (string) ($docente->codigo ?? $docente->id);

        // 4. Identificação única (INEP) da pessoa
        $f4 = $this->extractCode($docente->codigo_inep ?? $docente->id_inep ?? '');

        // 5. Código da Turma na Entidade/Escola
        $f5 = (string) ($firstTurma?->codigo ?? $firstTurma?->id ?? '');

        // 6. Código da turma no INEP
        $f6 = $this->extractCode($firstTurma?->codigo_inep ?? '');

        // 7. Função que exerce na turma (1=Docente, 2=Auxiliar de regência, 3=Monitor, 4=Tradutor Libras)
        $f7 = '1';

        // 8. Situação funcional / Regime de contratação / Tipo de vínculo (1=Concursado, 2=Temporário, 3=CLT)
        $depAdmin = $this->mapDependenciaAdministrativa($unidade->dependencia_administrativa ?? '4');
        $f8 = $depAdmin === '4' ? '3' : '1';

        // 9 a 33. Códigos das disciplinas lecionadas (1 a 25)
        // Se for Ensino Fundamental / Médio, define 1 (Língua Portuguesa), o restante vazio
        $discFields = array_fill(0, 25, '');
        $discFields[0] = '1'; // 1 = Língua Portuguesa / Componente Principal

        // 34. Linguagens e suas tecnologias (0 ou 1)
        $f34 = '0';

        // 35. Matemática e suas tecnologias (0 ou 1)
        $f35 = '0';

        // 36. Ciências da natureza e suas tecnologias (0 ou 1)
        $f36 = '0';

        // 37. Ciências humanas e sociais aplicadas (0 ou 1)
        $f37 = '0';

        // 38. Leciona no Itinerário de formação técnica e profissional (IFTP) (0 ou 1)
        $f38 = '0';

        $fields = array_merge(
            [$f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8],
            $discFields,
            [$f34, $f35, $f36, $f37, $f38]
        );

        return implode('|', $fields);
    }

    // =========================================================================
    // Registro 60 – Vínculo do Aluno com a Turma/Escola
    // Layout Oficial Educacenso 2026: EXATAMENTE 33 campos separados por |
    // =========================================================================
    private function buildRegistro60(Unidade $unidade, Turma $turma, Matricula $matricula): string
    {
        $pessoa = $matricula->pessoa;
        $instituicao = $unidade->instituicaoEnsino;

        // 1. Tipo de registro
        $f1 = '60';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? $instituicao?->codigo_inep ?? '');

        // 3. Código da pessoa no sistema
        $f3 = (string) ($pessoa->codigo ?? $pessoa->id);

        // 4. Identificação única (INEP) da pessoa
        $f4 = $this->extractCode($pessoa->codigo_inep ?? $pessoa->id_inep ?? '');

        // 5. Código da turma no sistema
        $f5 = (string) ($turma->codigo ?? $turma->id);

        // 6. Código da turma no INEP
        $f6 = $this->extractCode($turma->codigo_inep ?? '');

        // 7. Código da matrícula do aluno
        $f7 = (string) $matricula->id;

        // 8. Turma multi (0=Não)
        $f8 = '0';

        // 9. Carga horária integralizada pelo aluno (em horas) [novo em 2026]
        $f9 = '';

        // 10 a 20. Recursos de AEE na turma (0=Não)
        $f10 = $f11 = $f12 = $f13 = $f14 = $f15 = $f16 = $f17 = $f18 = $f19 = $f20 = '0';

        // 21. Recebe escolarização em outro espaço (0=Não)
        $f21 = '0';

        // 22. Transporte escolar público (0=Não)
        $f22 = '0';

        // 23 a 33. Poder público responsável e modalidades de transporte escolar (nulos quando f22==0)
        $f23 = $f24 = $f25 = $f26 = $f27 = $f28 = $f29 = $f30 = $f31 = $f32 = $f33 = '';

        $fields = [
            $f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9, $f10,
            $f11, $f12, $f13, $f14, $f15, $f16, $f17, $f18, $f19, $f20,
            $f21, $f22, $f23, $f24, $f25, $f26, $f27, $f28, $f29, $f30,
            $f31, $f32, $f33,
        ];

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
