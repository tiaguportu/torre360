<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\ActivityLogger;
use Tests\TestCase;

class AuditMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cria a tabela audit_logs em memória para o teste sem problemas de chave estrangeira
        Schema::create('audit_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event');
            $table->string('url')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });

        // Define uma rota de teste protegida pelo middleware
        Route::middleware([AuditMiddleware::class])
            ->get('/test-route-audit', function () {
                return response('ok');
            });
    }

    #[Test]
    public function deve_registrar_activity_log_para_usuario_com_role_responsavel()
    {
        $user = $this->createMock(User::class);
        $user->method('hasRole')->willReturnCallback(function ($role) {
            return $role === 'responsavel';
        });
        // Mock das propriedades do Model para não tentar acessar banco
        $user->id = 1;
        $user->name = 'Test User';
        $user->email = 'test@example.com';

        // Mock do Spatie ActivityLogger
        $activityLogger = $this->mock(ActivityLogger::class);
        $activityLogger->shouldIgnoreMissing();
        $activityLogger->shouldReceive('withProperties')
            ->once()
            ->with($this->callback(function ($properties) {
                return $properties['url'] === url('/test-route-audit')
                    && $properties['method'] === 'GET';
            }))
            ->andReturnSelf();

        $activityLogger->shouldReceive('log')
            ->once()
            ->with($this->callback(function ($message) {
                return str_contains($message, 'Responsável visualizou recurso: Test-Route-Audit');
            }));

        $response = $this->actingAs($user)->get('/test-route-audit');

        $response->assertStatus(200);
    }

    #[Test]
    public function deve_registrar_activity_log_para_usuario_com_role_professor()
    {
        $user = $this->createMock(User::class);
        $user->method('hasRole')->willReturnCallback(function ($role) {
            return $role === 'professor';
        });
        $user->id = 1;

        $activityLogger = $this->mock(ActivityLogger::class);
        $activityLogger->shouldIgnoreMissing();
        $activityLogger->shouldReceive('withProperties')->once()->andReturnSelf();
        $activityLogger->shouldReceive('log')
            ->once()
            ->with($this->callback(function ($message) {
                return str_contains($message, 'Professor visualizou recurso: Test-Route-Audit');
            }));

        $response = $this->actingAs($user)->get('/test-route-audit');

        $response->assertStatus(200);
    }

    #[Test]
    public function deve_registrar_activity_log_para_usuario_com_role_secretaria()
    {
        $user = $this->createMock(User::class);
        $user->method('hasRole')->willReturnCallback(function ($role) {
            return $role === 'secretaria';
        });
        $user->id = 1;

        $activityLogger = $this->mock(ActivityLogger::class);
        $activityLogger->shouldIgnoreMissing();
        $activityLogger->shouldReceive('withProperties')->once()->andReturnSelf();
        $activityLogger->shouldReceive('log')
            ->once()
            ->with($this->callback(function ($message) {
                return str_contains($message, 'Secretaria visualizou recurso: Test-Route-Audit');
            }));

        $response = $this->actingAs($user)->get('/test-route-audit');

        $response->assertStatus(200);
    }

    #[Test]
    public function deve_priorizar_role_da_sessao_no_activity_log()
    {
        $user = $this->createMock(User::class);
        // O usuário possui ambas as roles no hasRole, mas a da sessão é priorizada
        $user->method('hasRole')->willReturn(true);
        $user->id = 1;

        $activityLogger = $this->mock(ActivityLogger::class);
        $activityLogger->shouldIgnoreMissing();
        $activityLogger->shouldReceive('withProperties')->once()->andReturnSelf();
        $activityLogger->shouldReceive('log')
            ->once()
            ->with($this->callback(function ($message) {
                return str_contains($message, 'Secretaria visualizou recurso: Test-Route-Audit');
            }));

        $response = $this->actingAs($user)
            ->withSession(['active_role' => 'secretaria'])
            ->get('/test-route-audit');

        $response->assertStatus(200);
    }

    #[Test]
    public function nao_deve_registrar_activity_log_para_outras_roles()
    {
        $user = $this->createMock(User::class);
        $user->method('hasRole')->willReturn(false);
        $user->id = 1;

        // O logger não deve receber nenhuma chamada de log
        $activityLogger = $this->mock(ActivityLogger::class);
        $activityLogger->shouldIgnoreMissing();
        $activityLogger->shouldNotReceive('log');

        $response = $this->actingAs($user)->get('/test-route-audit');

        $response->assertStatus(200);
    }
}
