<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\Laravel\Middleware;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Laravel\Middleware\ValidateWebhook;
use Clubify\Checkout\Utils\Crypto\HMACSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;

/**
 * Testes para ValidateWebhook Middleware - Multi-Tenant Support
 *
 * Testa todos os cenários de validação de webhooks em ambientes
 * single-tenant e multi-tenant, incluindo:
 * - Resolução de secrets via callback customizado
 * - Obtenção de organization_id via header e payload
 * - Busca de secrets no model Organization
 * - Fallback para configuração global
 * - Validação de assinaturas HMAC
 * - Tratamento de erros e edge cases
 *
 * @group unit
 * @group laravel
 * @group middleware
 * @group webhook
 */
class ValidateWebhookMultiTenantTest extends TestCase
{
    private ValidateWebhook $middleware;
    private ClubifyCheckoutSDK|MockInterface $sdk;
    private Configuration|MockInterface $config;
    private HMACSignature|MockInterface $hmac;
    private string $testSecret = 'test-webhook-secret-123';
    private string $testOrganizationId = 'org_123456789';

    /**
     * Setup executado antes de cada teste
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Setup mocks específicos para este teste
        $this->hmac = Mockery::mock(HMACSignature::class);
        $this->config = Mockery::mock(Configuration::class);
        $this->sdk = Mockery::mock(ClubifyCheckoutSDK::class);

        // SDK sempre retorna config
        $this->sdk->shouldReceive('getConfig')
            ->andReturn($this->config)
            ->byDefault();

        // SDK getVersion para metadata
        $this->sdk->shouldReceive('getVersion')
            ->andReturn('1.0.0')
            ->byDefault();

        // Cria middleware
        $this->middleware = new ValidateWebhook($this->sdk, $this->hmac);
    }

    /**
     * Teardown executado após cada teste
     */
    protected function tearDown(): void
    {
        // Limpa mocks do Mockery
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @test
     * Testa que o secret é obtido do callback customizado quando configurado
     */
    public function it_gets_secret_from_custom_resolver_callback(): void
    {
        // Arrange: Configura callback customizado
        $callbackSecret = 'custom-callback-secret';
        $callbackCalled = false;

        $callback = function (Request $request) use ($callbackSecret, &$callbackCalled) {
            $callbackCalled = true;
            $this->assertInstanceOf(Request::class, $request);
            return $callbackSecret;
        };

        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn($callback);

        // Mock HMAC verification
        $this->hmac->shouldReceive('verify')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('string'), $callbackSecret)
            ->andReturn(true);

        $request = $this->createValidWebhookRequest();

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertTrue($callbackCalled, 'Callback should have been called');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * Testa que organization_id é obtido do header X-Organization-ID
     */
    public function it_gets_organization_id_from_header(): void
    {
        // Arrange: Request com header X-Organization-ID
        $request = $this->createValidWebhookRequest([
            'X-Organization-ID' => $this->testOrganizationId,
        ]);

        // Não há callback customizado
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        // Mock organization model lookup
        $this->setupOrganizationModelMock($this->testOrganizationId, $this->testSecret);

        // Mock HMAC verification
        $this->hmac->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * Testa que organization_id é obtido do payload como fallback
     */
    public function it_gets_organization_id_from_payload_as_fallback(): void
    {
        // Arrange: Request SEM header mas COM organization_id no payload
        $payload = [
            'event' => 'order.created',
            'data' => [
                'organization_id' => $this->testOrganizationId,
                'order_id' => 'order_123',
            ],
            'timestamp' => time(),
        ];

        $request = $this->createValidWebhookRequest([], $payload);

        // Não há callback customizado
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        // Mock organization model lookup
        $this->setupOrganizationModelMock($this->testOrganizationId, $this->testSecret);

        // Mock HMAC verification
        $this->hmac->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * Testa que organization_id é obtido do payload.data.organizationId (camelCase)
     */
    public function it_gets_organization_id_from_payload_camel_case(): void
    {
        // Arrange: Payload com organizationId (camelCase)
        $payload = [
            'event' => 'order.created',
            'data' => [
                'organizationId' => $this->testOrganizationId,
                'order_id' => 'order_123',
            ],
            'timestamp' => time(),
        ];

        $request = $this->createValidWebhookRequest([], $payload);

        // Não há callback customizado
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        // Mock organization model lookup
        $this->setupOrganizationModelMock($this->testOrganizationId, $this->testSecret);

        // Mock HMAC verification
        $this->hmac->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * Testa que secret é obtido do model Organization.settings
     */
    public function it_gets_secret_from_organization_model_settings(): void
    {
        // Arrange
        $request = $this->createValidWebhookRequest([
            'X-Organization-ID' => $this->testOrganizationId,
        ]);

        // Não há callback
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        // Mock organization model com settings
        $this->setupOrganizationModelMock(
            $this->testOrganizationId,
            $this->testSecret,
            'settings'
        );

        // Mock HMAC verification
        $this->hmac->shouldReceive('verify')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('string'), $this->testSecret)
            ->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * Testa fallback para config global quando não há organization_id
     */
    public function it_falls_back_to_global_config_for_single_tenant(): void
    {
        // Arrange: Request sem organization_id
        $payload = [
            'event' => 'order.created',
            'data' => [
                'order_id' => 'order_123',
            ],
            'timestamp' => time(),
        ];

        $request = $this->createValidWebhookRequest([], $payload);

        // Não há callback customizado
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        // Mock organization model não existe ou não encontra
        $this->config->shouldReceive('get')
            ->with('webhook.organization_model', '\\App\\Models\\Organization')
            ->andReturn('NonExistentClass');

        // Fallback para config global
        $this->config->shouldReceive('get')
            ->with('webhook.secret')
            ->andReturn($this->testSecret);

        // Mock HMAC verification
        $this->hmac->shouldReceive('verify')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('string'), $this->testSecret)
            ->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * Testa que exceção é lançada quando nenhum secret está configurado
     */
    public function it_throws_exception_when_no_secret_configured(): void
    {
        // Arrange: Nenhum secret configurado
        $request = $this->createValidWebhookRequest();

        // Não há callback
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        // Não há organization model
        $this->config->shouldReceive('get')
            ->with('webhook.organization_model', '\\App\\Models\\Organization')
            ->andReturn('NonExistentClass');

        // Não há config global
        $this->config->shouldReceive('get')
            ->with('webhook.secret')
            ->andReturn(null);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Webhook secret não configurado', $data['message']);
    }

    /**
     * @test
     * Testa que organization_id inválido é tratado gracefully
     */
    public function it_handles_invalid_organization_id_gracefully(): void
    {
        // Arrange: Organization ID que não existe
        $invalidOrgId = 'org_nonexistent';
        $request = $this->createValidWebhookRequest([
            'X-Organization-ID' => $invalidOrgId,
        ]);

        // Não há callback
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        // Mock organization model retorna null (não encontrado)
        $this->setupOrganizationModelMock($invalidOrgId, null);

        // Fallback para config global
        $this->config->shouldReceive('get')
            ->with('webhook.secret')
            ->andReturn($this->testSecret);

        // Mock HMAC verification
        $this->hmac->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * Testa validação de webhook com assinatura correta
     */
    public function it_validates_webhook_signature_with_correct_secret(): void
    {
        // Arrange: Payload e assinatura válidos
        $payload = [
            'event' => 'order.created',
            'data' => ['order_id' => 'order_123'],
            'timestamp' => time(),
        ];

        $request = $this->createValidWebhookRequest([], $payload);

        // Config global
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        $this->config->shouldReceive('get')
            ->with('webhook.organization_model', '\\App\\Models\\Organization')
            ->andReturn('NonExistentClass');

        $this->config->shouldReceive('get')
            ->with('webhook.secret')
            ->andReturn($this->testSecret);

        // HMAC retorna TRUE (assinatura válida)
        $this->hmac->shouldReceive('verify')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('string'), $this->testSecret)
            ->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            // Verifica que atributos foram adicionados ao request
            $this->assertNotNull($req->attributes->get('webhook_payload'));
            $this->assertNotNull($req->attributes->get('webhook_event'));
            $this->assertNotNull($req->attributes->get('webhook_data'));
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * Testa que webhook com assinatura inválida é rejeitado
     */
    public function it_rejects_webhook_with_invalid_signature(): void
    {
        // Arrange
        $request = $this->createValidWebhookRequest();

        // Config
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        $this->config->shouldReceive('get')
            ->with('webhook.organization_model', '\\App\\Models\\Organization')
            ->andReturn('NonExistentClass');

        $this->config->shouldReceive('get')
            ->with('webhook.secret')
            ->andReturn($this->testSecret);

        // HMAC retorna FALSE (assinatura inválida)
        $this->hmac->shouldReceive('verify')
            ->once()
            ->andReturn(false);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Middleware should not call next() with invalid signature');
        });

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Assinatura do webhook inválida', $data['message']);
    }

    /**
     * @test
     * Testa que header organization_id tem prioridade sobre payload
     */
    public function header_organization_id_has_priority_over_payload(): void
    {
        // Arrange: Organization ID diferente no header e no payload
        $headerOrgId = 'org_from_header';
        $payloadOrgId = 'org_from_payload';

        $payload = [
            'event' => 'order.created',
            'data' => [
                'organization_id' => $payloadOrgId,
                'order_id' => 'order_123',
            ],
            'timestamp' => time(),
        ];

        $request = $this->createValidWebhookRequest([
            'X-Organization-ID' => $headerOrgId,
        ], $payload);

        // Não há callback
        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn(null);

        // Deve buscar usando headerOrgId (não payloadOrgId)
        $this->setupOrganizationModelMock($headerOrgId, $this->testSecret);

        // Mock HMAC
        $this->hmac->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return new JsonResponse(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * Testa que timestamp expirado é rejeitado
     */
    public function it_rejects_expired_webhook_timestamp(): void
    {
        // Arrange: Timestamp de 10 minutos atrás (> 5 min tolerance)
        $oldTimestamp = time() - 600; // 10 minutos
        $payload = [
            'event' => 'order.created',
            'data' => ['order_id' => 'order_123'],
            'timestamp' => $oldTimestamp,
        ];

        $request = $this->createValidWebhookRequest([
            'X-Clubify-Timestamp' => (string)$oldTimestamp,
        ], $payload);

        // Config
        $this->config->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with('webhook.secret')
            ->andReturn($this->testSecret);

        // HMAC válido (mas timestamp inválido)
        $this->hmac->shouldReceive('verify')->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not process expired webhook');
        });

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('expirado', $data['message']);
    }

    /**
     * @test
     * Testa que timestamp do futuro é rejeitado
     */
    public function it_rejects_future_webhook_timestamp(): void
    {
        // Arrange: Timestamp do futuro (+ 10 minutos)
        $futureTimestamp = time() + 600;
        $payload = [
            'event' => 'order.created',
            'data' => ['order_id' => 'order_123'],
            'timestamp' => $futureTimestamp,
        ];

        $request = $this->createValidWebhookRequest([
            'X-Clubify-Timestamp' => (string)$futureTimestamp,
        ], $payload);

        // Config
        $this->config->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with('webhook.secret')
            ->andReturn($this->testSecret);

        // HMAC válido
        $this->hmac->shouldReceive('verify')->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not process future webhook');
        });

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('futuro', $data['message']);
    }

    /**
     * @test
     * Testa que payload sem campos obrigatórios é rejeitado
     */
    public function it_rejects_webhook_with_missing_required_fields(): void
    {
        // Arrange: Payload sem campo 'event'
        $invalidPayload = [
            'data' => ['order_id' => 'order_123'],
            'timestamp' => time(),
            // Falta 'event'
        ];

        $request = $this->createValidWebhookRequest([], $invalidPayload);

        // Config
        $this->config->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with('webhook.secret')
            ->andReturn($this->testSecret);

        // HMAC válido
        $this->hmac->shouldReceive('verify')->andReturn(true);

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not process invalid webhook');
        });

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('obrigatório', $data['message']);
    }

    /**
     * @test
     * Testa que exception no callback customizado é tratada
     */
    public function it_handles_exception_in_custom_resolver(): void
    {
        // Arrange: Callback que lança exception
        $callback = function () {
            throw new \RuntimeException('Custom resolver error');
        };

        $this->config->shouldReceive('get')
            ->with('webhook.secret_resolver')
            ->andReturn($callback);

        $request = $this->createValidWebhookRequest();

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not proceed with exception in resolver');
        });

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('webhook.secret_resolver', $data['message']);
    }

    /**
     * Helper: Cria request de webhook válido
     */
    private function createValidWebhookRequest(array $headers = [], array $payload = null): Request
    {
        $payload = $payload ?? [
            'event' => 'order.created',
            'data' => ['order_id' => 'order_123'],
            'timestamp' => time(),
        ];

        $payloadJson = json_encode($payload);

        $defaultHeaders = [
            'X-Clubify-Signature' => 'valid_signature_hash',
            'X-Clubify-Timestamp' => (string)time(),
            'Content-Type' => 'application/json',
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        // Cria request mock
        $request = Request::create(
            '/webhook',
            'POST',
            [],
            [],
            [],
            [],
            $payloadJson
        );

        // Adiciona headers
        foreach ($allHeaders as $key => $value) {
            $request->headers->set($key, $value);
        }

        return $request;
    }

    /**
     * Helper: Setup mock do model Organization
     */
    private function setupOrganizationModelMock(
        string $organizationId,
        ?string $secret,
        string $location = 'settings'
    ): void {
        // Mock class exists check
        $mockClass = Mockery::mock('alias:App\Models\Organization');

        if ($secret === null) {
            // Organization não encontrada
            $mockClass->shouldReceive('find')
                ->with($organizationId)
                ->andReturn(null);
        } else {
            // Organization encontrada com secret
            // Cria mock object que simula propriedades do Eloquent Model
            $org = new \stdClass();

            if ($location === 'settings') {
                // Simula array de settings
                $org->settings = [
                    'clubify_checkout_webhook_secret' => $secret,
                ];
            } elseif ($location === 'direct') {
                // Simula campo direto
                $org->webhook_secret = $secret;
            }

            $mockClass->shouldReceive('find')
                ->with($organizationId)
                ->andReturn($org);
        }

        // Config para organization model
        $this->config->shouldReceive('get')
            ->with('webhook.organization_model', '\\App\\Models\\Organization')
            ->andReturn('App\\Models\\Organization');

        $this->config->shouldReceive('get')
            ->with('webhook.organization_secret_key', 'clubify_checkout_webhook_secret')
            ->andReturn('clubify_checkout_webhook_secret');
    }
}
