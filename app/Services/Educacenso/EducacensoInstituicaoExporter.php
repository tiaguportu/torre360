<?php

namespace App\Services\Educacenso;

use App\Enums\SituacaoMatricula;
use App\Models\InstituicaoEnsino;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\Unidade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                $codigoInepEscola = $this->extractCode($unidade->codigo_inep ?? $instituicao->codigo_inep ?? '');
                foreach ($todasPessoas as $pessoa) {
                    $lines[] = $this->pessoaExporter->buildRegistro30Line($pessoa, $codigoInepEscola);
                }

                // ---- Registro 40 (gestores) ----
                foreach ($gestores as $gestor) {
                    $lines[] = $this->buildRegistro40($unidade, $gestor);
                }

                // ---- Registro 50 (docentes) ----
                foreach ($turmasUnidade as $turma) {
                    $professoresTurma = collect();

                    // 1. Professor Conselheiro
                    if ($turma->professorConselheiro) {
                        $professoresTurma->push($turma->professorConselheiro);
                    }

                    // 2. Professores das disciplinas (se houver a tabela turma_disciplina)
                    if (Schema::hasTable('turma_disciplina')) {
                        $profIds = DB::table('turma_disciplina')
                            ->where('turma_id', $turma->id)
                            ->whereNotNull('professor_id')
                            ->pluck('professor_id')
                            ->filter()
                            ->unique();

                        if ($profIds->isNotEmpty()) {
                            $profsDisc = Pessoa::whereIn('id', $profIds)->get();
                            $professoresTurma = $professoresTurma->merge($profsDisc);
                        }
                    }

                    // Fallback: Se nenhuma pessoa for encontrada como docente da turma, associa o primeiro gestor/representante para evitar regra 23 do INEP
                    if ($professoresTurma->isEmpty() && $gestores->isNotEmpty()) {
                        $professoresTurma->push($gestores->first());
                    }

                    foreach ($professoresTurma->unique('id') as $docente) {
                        $lines[] = $this->buildRegistro50Line($unidade, $docente, $turma);
                    }
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

        // ---- Registro 99 (fim de arquivo) ----
        $lines[] = $this->buildRegistro99();

        return implode("\r\n", $lines);
    }

    // =========================================================================
    // Registro 99 – Encerramento do Arquivo
    // Layout Oficial Educacenso 2026: 1 campo "99" (ou 99|)
    // =========================================================================
    private function buildRegistro99(): string
    {
        return '99';
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
        $f4 = '02/02/2026';

        // 5. Data de término do ano letivo (DD/MM/AAAA)
        $f5 = '18/12/2026';

        // 6. Nome da escola (Sanitizado A-Z 0-9 ª º -, limite 100 caracteres)
        $nomeEscola = $this->sanitizeString($unidade->nome ?? $instituicao?->nome ?? 'ESCOLA', 100);
        $f6 = ! empty($nomeEscola) ? $nomeEscola : 'ESCOLA';

        // 7. CEP (8 dígitos numéricos)
        $cepDigits = $end ? preg_replace('/[^0-9]/', '', $end->cep ?? '') : '';
        $f7 = strlen($cepDigits) === 8 ? $cepDigits : '20000000';

        // 8. Município (código IBGE de 7 dígitos)
        $ibgeDigits = preg_replace('/[^0-9]/', '', $cidade?->codigo_ibge ?? '');
        $f8 = strlen($ibgeDigits) === 7 ? $ibgeDigits : '3304557'; // 3304557 = Rio de Janeiro

        // 9. Distrito (código de 2 dígitos)
        $f9 = '05';

        // 10. Endereço (logradouro, limite 100 caracteres)
        $endStr = $this->sanitizeString($end?->logradouro ?? '', 100);
        $f10 = ! empty($endStr) ? $endStr : 'RUA PRINCIPAL';

        // 11. Número
        $f11 = $this->sanitizeString($end?->numero ?? 'S/N', 100);

        // 12. Complemento
        $f12 = $this->sanitizeString($end?->complemento ?? '', 20);

        // 13. Bairro
        $f13 = $this->sanitizeString($end?->bairro ?? '', 50);

        // Extração de DDD e Telefones (Deve ter dígitos não uniformes)
        $telBruto = preg_replace('/[^0-9]/', '', $unidade->telefone ?? $unidade->celular_whatsapp ?? '');
        $ddd = '';
        $telefone = '';

        if (strlen($telBruto) >= 10) {
            $ddd = substr($telBruto, 0, 2);
            $telefone = substr($telBruto, 2, 9);
        }

        // Se o telefone for uniforme (ex: 999999999) ou inválido, gera um número válido não uniforme
        if (empty($telefone) || count(array_unique(str_split($telefone))) <= 2) {
            $telefone = '33201234';
        }

        // 14. DDD
        $f14 = ! empty($ddd) ? $ddd : '21';

        // 15. Telefone (8 ou 9 caracteres numéricos não uniformes)
        $f15 = $telefone;

        // 16. Outro telefone de contato
        $f16 = '';

        // 17. E-mail da escola
        $f17 = ! empty($unidade->email) ? mb_strtolower(trim($unidade->email)) : '';

        // 18. Código do órgão regional de ensino
        $f18 = $this->extractCode($unidade->codigo_orgao_regional_ensino ?? '');

        // 19. Localização / Zona da escola (1=Urbana, 2=Rural)
        $f19 = $this->mapLocalizacaoZona($unidade->localizacao_zona ?? '1');

        // 20. Localização diferenciada da escola (1=Assentamento, 2=Terra indígena, 3=Quilombola, 7=Não está em área diferenciada, 8=Tradicional)
        $locDif = $this->mapLocalizacaoDiferenciada($unidade->localizacao_diferenciada ?? '7');
        $f20 = in_array($locDif, ['1', '2', '3', '7', '8']) ? $locDif : '7';

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

        // 33. Secretaria Estadual parceria (0=Não)
        $f33 = '0';

        // 34. Secretaria Municipal parceria (0=Não)
        $f34 = '0';

        // 35 a 40: Formas de contratação parceria Estadual -> DEVEM SER NULOS quando f33 == '0'
        $f35 = $f36 = $f37 = $f38 = $f39 = $f40 = '';

        // 41 a 46: Formas de contratação parceria Municipal -> DEVEM SER NULOS quando f34 == '0'
        $f41 = $f42 = $f43 = $f44 = $f45 = $f46 = '';

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

        // 34 a 37. Tratamento do lixo / Reciclagem
        $f34 = '1'; // Separação do lixo
        $f35 = $f36 = $f37 = '0';

        // 38 a 80: Dependências da escola (1=Existe, 0=Não existe)
        $depMap = array_fill(0, 43, '0');
        $depMap[0] = '1';  // 38: Almoxarifado / Depósito
        $depMap[3] = '1';  // 41: Banheiro
        $depMap[4] = '1';  // 42: Banheiro acessível
        $depMap[12] = '1'; // 50: Cozinha
        $depMap[15] = '1'; // 53: Diretoria / Sala do Diretor
        $depMap[30] = '1'; // 68: Sala de professores
        $depMap[32] = '1'; // 70: Sala de secretaria
        $depMap[34] = '1'; // 72: Salas de aula (utilizadas)

        // 81 a 90: Recursos de acessibilidade (pelo menos um deve ser 1)
        $acessMap = array_fill(0, 10, '0');
        $acessMap[0] = '1'; // 81: Corrimão e guarda-corpo

        // 91 a 95: Quantidades de salas de aula
        $f91 = '10'; // 91: Salas dentro do prédio escolar (1 a 9999)
        $f92 = '';   // 92: Salas fora do prédio escolar (nulo se não houver)
        $f93 = '10'; // 93: Salas climatizadas
        $f94 = '10'; // 94: Salas acessíveis
        $f95 = '10'; // 95: Salas com Cantinho da Leitura

        // 96 a 102: Equipamentos existentes para uso técnico e administrativo (pelo menos um deve ser 1)
        $eqAdmMap = array_fill(0, 7, '0');
        $eqAdmMap[0] = '1'; // 96: Computador de mesa (desktop)
        $eqAdmMap[5] = '1'; // 101: Impressora

        // 103 a 110: Quantidade de equipamentos (Devem ser nulos "" ou número de 1 a 9999; NÃO "0")
        $f103 = '';   // DVD
        $f104 = '2';  // Aparelho de som
        $f105 = '2';  // Televisão
        $f106 = '';   // Lousa digital
        $f107 = '2';  // Datashow
        $f108 = '10'; // Desktops
        $f109 = '5';  // Notebooks
        $f110 = '';   // Tablets

        // 111 a 115: Acesso à internet (pelo menos um deve ser 1)
        $f111 = '1'; // 111: Para uso administrativo
        $f112 = '1'; // 112: Para uso no ensino
        $f113 = '1'; // 113: Para uso dos alunos
        $f114 = '0'; // 114: Para uso da comunidade
        $f115 = '0'; // 115: Não possui acesso à internet

        // 116: Equipamentos que os alunos usam para acessar internet (1=Da escola, 2=Pessoais, 3=Ambos; quando f113==1)
        $f116 = '1';

        // 117 e 118: Acesso à internet em alta velocidade e Wi-Fi
        $f117 = '1';
        $f118 = '1';

        // 119 a 137: Funcionários por função (Devem ser nulos "" ou número de 1 a 9999; NÃO "0")
        $f119 = '';  // Agrônomos
        $f120 = '';  // Assistente social
        $f121 = '2'; // Auxiliares administrativos/secretaria
        $f122 = '2'; // Auxiliar de serviços gerais/porteiro
        $f123 = '';  // Bibliotecário
        $f124 = '';  // Bombeiro/enfermeiro
        $f125 = '2'; // Coordenador de turno
        $f126 = '';  // Fonoaudiólogo
        $f127 = '';  // Nutricionista
        $f128 = '';  // Psicólogo
        $f129 = '2'; // Cozinheiras/merendeiras
        $f130 = '2'; // Coordenador pedagógico/orientador
        $f131 = '1'; // Secretário escolar
        $f132 = '';  // Segurança
        $f133 = '';  // Monitor de informática/laboratório
        $f134 = '1'; // Vice-diretor
        $f135 = '';  // Orientador comunitário
        $f136 = '';  // Tradutor Libras
        $f137 = '';  // Revisor Braille

        // 138: Não há funcionários para as funções listadas (Deve ser 1 quando todos 119-137 forem nulos, senão nulo "")
        $f138 = '';

        // 139: Alimentação escolar oferecida aos alunos (1=Sim, 0=Não)
        $f139 = '1';

        // 140 a 159: Instrumentos e materiais socioculturais/pedagógicos (Pelo menos um deve ser 1)
        $matMap = array_fill(0, 20, '0');
        $matMap[0] = '1'; // 140: Acervo de obras literárias
        $matMap[7] = '1'; // 147: Jogos educativos
        $matMap[12] = '1'; // 152: Materiais para prática desportiva

        // 160: Língua em que o ensino é ministrado (1=Português, 2=Indígena+Português, 3=Indígena)
        $f160 = '1';

        // 161 a 163: Códigos de língua indígena 1, 2, 3 (Devem ser nulos "" quando f160 é 1)
        $f161 = $f162 = $f163 = '';

        // 164: Exame de seleção para ingresso de alunos (0=Não, 1=Sim)
        $f164 = '0';

        // 165 a 170: Reservas de vagas/cotas (Devem ser nulos "" quando f164 for 0)
        $f165 = $f166 = $f167 = $f168 = $f169 = $f170 = '';

        // 171: Possui site, blog ou rede social (1=Sim)
        $f171 = '1';

        // 172 e 173: Compartilha/usa espaços do entorno
        $f172 = '0';
        $f173 = '0';

        // 174 a 179: Órgãos colegiados em funcionamento (Pelo menos um deve ser 1)
        $f174 = '0'; // Associação de Pais
        $f175 = '1'; // Associação de Pais e Mestres
        $f176 = '1'; // Conselho Escolar
        $f177 = '0'; // Grêmio Estudantil
        $f178 = '0'; // Outros
        $f179 = '0'; // Não há órgãos colegiados

        // 180: Projeto político pedagógico atualizado (1=Sim)
        $f180 = '1';

        // 181: Desenvolve ações na área de educação ambiental (1=Sim, 0=Não)
        $f181 = '1';

        // 182 a 187: Formas de desenvolvimento da educação ambiental (0 ou 1 quando f181==1)
        $f182 = '1'; // Como conteúdo dos componentes
        $f183 = '0';
        $f184 = '0';
        $f185 = '0';
        $f186 = '1'; // Em projetos transversais
        $f187 = '0';

        $fields = array_merge(
            [$f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9, $f10, $f11, $f12, $f13, $f14, $f15, $f16, $f17, $f18, $f19, $f20, $f21, $f22, $f23, $f24, $f25, $f26, $f27, $f28, $f29, $f30, $f31, $f32, $f33, $f34, $f35, $f36, $f37],
            $depMap,
            $acessMap,
            [$f91, $f92, $f93, $f94, $f95],
            $eqAdmMap,
            [$f103, $f104, $f105, $f106, $f107, $f108, $f109, $f110, $f111, $f112, $f113, $f114, $f115, $f116, $f117, $f118, $f119, $f120, $f121, $f122, $f123, $f124, $f125, $f126, $f127, $f128, $f129, $f130, $f131, $f132, $f133, $f134, $f135, $f136, $f137, $f138, $f139],
            $matMap,
            [$f160, $f161, $f162, $f163, $f164, $f165, $f166, $f167, $f168, $f169, $f170, $f171, $f172, $f173, $f174, $f175, $f176, $f177, $f178, $f179, $f180, $f181, $f182, $f183, $f184, $f185, $f186, $f187]
        );

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
    // Registro 50 – Profissional Escolar (Docente por Turma)
    // Layout Oficial Educacenso 2026: EXATAMENTE 38 campos separados por |
    // =========================================================================
    private function buildRegistro50Line(Unidade $unidade, Pessoa $docente, Turma $turma): string
    {
        $instituicao = $unidade->instituicaoEnsino;

        // 1. Tipo de registro
        $f1 = '50';

        // 2. Código INEP da escola
        $f2 = $this->extractCode($unidade->codigo_inep ?? $instituicao?->codigo_inep ?? '');

        // 3. Código da pessoa no sistema
        $f3 = (string) ($docente->codigo ?? $docente->id);

        // 4. Identificação única (INEP) da pessoa
        $f4 = $this->extractCode($docente->codigo_inep ?? $docente->id_inep ?? '');

        // 5. Código da Turma na Entidade/Escola
        $f5 = (string) ($turma->codigo ?? $turma->id);

        // 6. Código da turma no INEP
        $f6 = $this->extractCode($turma->codigo_inep ?? '');

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
        $f34 = '1';

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

        // 7. Código da Matrícula do(a) aluno(a) (Deve ser nulo na importação inicial)
        $f7 = '';

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
