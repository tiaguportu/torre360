<?php

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Models\Contrato;
use App\Models\DocumentoInserido;
use App\Models\HistoricoEscolar;
use App\Models\Interessado;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\TipoDocumento;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Ser da equipe" não basta para ver documentos pessoais, contratos, exportações e CRM: o professor tem acesso amplo
 * ao painel, mas não precisa do RG, do CPF nem do contrato de todos os alunos (LGPD art. 6º, III — necessidade).
 */
class AcessoDocumentosEquipeTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";

    private Matricula $matricula;

    private Pessoa $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        // Só os papéis: o professor começa sem nenhuma permissão extra, como no cadastro padrão. (O RolesSeeder completo
        // cria centenas de permissões e deixaria cada teste ~1 minuto mais lento.)
        foreach (['super_admin', 'admin', 'secretaria', 'coordenador', 'professor', 'responsavel'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }
        Storage::fake('local');
        Http::preventStrayRequests();

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => '5º Ano A', 'periodo_letivo_id' => $periodo->id]);
        $this->aluno = Pessoa::create(['nome' => 'Aluno Sigiloso', 'cpf' => '11122233344']);
        $this->matricula = Matricula::create(['pessoa_id' => $this->aluno->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        DocumentoInserido::create([
            'tipo_documento_id' => TipoDocumento::firstOrCreate(['nome' => 'RG do Aluno'])->id,
            'matricula_id' => $this->matricula->id,
            'status' => SituacaoDocumento::EM_ANALISE,
            'arquivo_path' => 'documentos_alunos/rg-aluno.pdf',
            'nome_arquivo_original' => 'rg.pdf',
            'hash_arquivo' => str_repeat('a', 64),
        ]);

        foreach ([
            'documentos_alunos/rg-aluno.pdf',
            'documentos_candidatos/9/certidao.pdf',
            'matriculas_online/9/rg.pdf',
            'atendimentos/anexos/laudo.pdf',
            'filament_exports/1/pessoas.pdf',
            'imports/extratos/extrato.pdf',
            'contratos/contrato-9.pdf',
            'audios-crm/conversa.pdf',
            'materiais-aula/apostila.pdf',
            'planos-aula/plano.pdf',
            'pessoas_fotos/aluno.pdf',
            'rotina-diaria/foto.pdf',
        ] as $caminho) {
            Storage::disk('local')->put($caminho, self::PDF);
        }
    }

    private function usuario(string $papel): User
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole($papel);

        return $user;
    }

    /** @return list<string> */
    private function arquivosSensiveis(): array
    {
        return [
            'documentos_alunos/rg-aluno.pdf',      // documento pessoal registrado
            'documentos_candidatos/9/certidao.pdf', // documento de candidato sem registro
            'matriculas_online/9/rg.pdf',
            'atendimentos/anexos/laudo.pdf',
            'filament_exports/1/pessoas.pdf',       // exportação com dados de todos (id sequencial)
            'imports/extratos/extrato.pdf',         // extrato bancário
            'contratos/contrato-9.pdf',
            'audios-crm/conversa.pdf',
        ];
    }

    /** @return list<string> */
    private function arquivosDoDiaADia(): array
    {
        return ['materiais-aula/apostila.pdf', 'planos-aula/plano.pdf', 'pessoas_fotos/aluno.pdf', 'rotina-diaria/foto.pdf'];
    }

    public function test_professor_nao_abre_documento_pessoal_exportacao_nem_contrato(): void
    {
        $professor = $this->usuario('professor');

        foreach ($this->arquivosSensiveis() as $caminho) {
            $this->actingAs($professor)->get('/visualizar-documento/'.$caminho)
                ->assertForbidden();
        }
    }

    public function test_professor_continua_abrindo_materiais_de_aula_e_fotos(): void
    {
        $professor = $this->usuario('professor');

        foreach ($this->arquivosDoDiaADia() as $caminho) {
            $this->actingAs($professor)->get('/visualizar-documento/'.$caminho)
                ->assertOk();
        }
    }

    public function test_professor_com_a_permissao_especifica_abre_o_documento_pessoal(): void
    {
        Permission::firstOrCreate(['name' => 'View:DocumentoInserido', 'guard_name' => 'web']);
        Role::findByName('professor')->givePermissionTo('View:DocumentoInserido');

        $professor = $this->usuario('professor');

        $this->actingAs($professor)->get('/visualizar-documento/documentos_alunos/rg-aluno.pdf')->assertOk();
        // A permissão é só dos documentos: exportações e extratos seguem fechados.
        $this->actingAs($professor)->get('/visualizar-documento/filament_exports/1/pessoas.pdf')->assertForbidden();
    }

    public function test_equipe_administrativa_continua_abrindo_tudo(): void
    {
        foreach (['super_admin', 'admin', 'secretaria', 'coordenador'] as $papel) {
            $usuario = $this->usuario($papel);

            foreach ([...$this->arquivosSensiveis(), ...$this->arquivosDoDiaADia()] as $caminho) {
                $this->actingAs($usuario)->get('/visualizar-documento/'.$caminho)
                    ->assertOk();
            }
        }
    }

    public function test_familia_abre_o_documento_do_proprio_filho_e_nao_o_de_outro(): void
    {
        $responsavel = Pessoa::create(['nome' => 'Mãe do Aluno', 'cpf' => '55566677788']);
        $responsavel->alunos()->attach($this->aluno);
        $mae = User::factory()->create(['activated_at' => now()]);
        $mae->pessoas()->attach($responsavel);

        $outra = User::factory()->create(['activated_at' => now()]);
        $outra->pessoas()->attach(Pessoa::create(['nome' => 'Outra Família', 'cpf' => '99988877766']));

        $this->actingAs($mae)->get('/visualizar-documento/documentos_alunos/rg-aluno.pdf')->assertOk();
        $this->actingAs($outra)->get('/visualizar-documento/documentos_alunos/rg-aluno.pdf')->assertForbidden();
    }

    public function test_regras_de_acesso_dos_modelos(): void
    {
        $professor = $this->usuario('professor');
        $secretaria = $this->usuario('secretaria');
        $documento = DocumentoInserido::firstOrFail();
        $contrato = Contrato::create(['matricula_id' => $this->matricula->id, 'valor_total' => 1000, 'data_aceite' => '2026-01-05']);

        // Documento pessoal e contrato: só a equipe administrativa (ou permissão específica).
        $this->assertFalse($documento->isAccessibleBy($professor));
        $this->assertFalse($contrato->isAccessibleBy($professor));
        $this->assertTrue($documento->isAccessibleBy($secretaria));
        $this->assertTrue($contrato->isAccessibleBy($secretaria));

        // A matrícula (boletim, listas de turma) segue acessível ao professor, que trabalha com ela todo dia.
        $this->assertTrue($this->matricula->isAccessibleBy($professor));
        $this->assertFalse($this->matricula->isAccessibleByFamilia($professor));

        Permission::firstOrCreate(['name' => 'View:Contrato', 'guard_name' => 'web']);
        Role::findByName('professor')->givePermissionTo('View:Contrato');
        $this->assertTrue($contrato->fresh()->isAccessibleBy($this->usuario('professor')));
    }

    public function test_professor_nao_gera_historico_escolar_nem_dossie_do_crm(): void
    {
        Http::preventStrayRequests();
        $professor = $this->usuario('professor');

        $historico = HistoricoEscolar::create([
            'pessoa_id' => $this->aluno->id,
            'codigo_autenticidade' => 'HIST-2026-ACESSO-0001',
            'situacao' => 'concluido',
            'data_emissao' => now(),
        ]);

        $this->actingAs($professor)->get("/historicos-escolares/{$historico->id}/pdf")->assertForbidden();
        $this->actingAs($professor)->get("/historicos-escolares/{$historico->id}/download")->assertForbidden();

        $interessado = Interessado::factory()->create();

        $this->actingAs($professor)->get("/crm/interessados/{$interessado->id}/dossie-pdf")->assertForbidden();
        $this->actingAs($professor)->get("/crm/interessados/{$interessado->id}/dossie-pdf/stream")->assertForbidden();
    }

    public function test_secretaria_continua_gerando_o_historico_escolar(): void
    {
        $historico = HistoricoEscolar::create([
            'pessoa_id' => $this->aluno->id,
            'codigo_autenticidade' => 'HIST-2026-ACESSO-0002',
            'situacao' => 'concluido',
            'data_emissao' => now(),
        ]);

        $this->actingAs($this->usuario('secretaria'))
            ->get("/historicos-escolares/{$historico->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
