<?php

namespace Tests\Feature\Api;

use App\Models\Balance;
use App\Models\Charge;
use App\Models\Project;
use App\Services\Security\TokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for public API middleware (authentication + origin validation).
 * Uses the payment-methods and charge pay endpoints as representative routes.
 */
final class PublicPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('xadd')->byDefault()->andReturn('ok');

        $this->project = $this->createProject();
    }

    // -------------------------------------------------------------------------
    // Payment methods listing
    // -------------------------------------------------------------------------

    #[Test]
    public function canListPaymentMethods(): void
    {
        $response = $this->withPublicKey()
            ->getJson('/api/v1/public/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure(['object', 'data'])
            ->assertJsonFragment(['object' => 'list']);

        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
    }

    // -------------------------------------------------------------------------
    // Public key authentication
    // -------------------------------------------------------------------------

    #[Test]
    public function publicRouteRequiresPublicKeyHeader(): void
    {
        $charge = $this->createCharge();

        $this->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
            'method' => 'pix',
        ])
            ->assertStatus(401)
            ->assertJsonFragment(['code' => 'missing_public_key']);
    }

    #[Test]
    public function publicRouteRejectsInvalidPublicKey(): void
    {
        $charge = $this->createCharge();

        $this->withHeader('X-Public-Key', 'pk_test_invalid')
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(401)
            ->assertJsonFragment(['code' => 'invalid_public_key']);
    }

    // -------------------------------------------------------------------------
    // Origin validation
    // -------------------------------------------------------------------------

    #[Test]
    public function publicRouteRejectsMissingOriginWhenOriginsConfigured(): void
    {
        $projectWithOrigins = $this->createProjectWithOrigins(['https://app.example.com']);
        $charge = $this->createCharge($projectWithOrigins);

        $this->withHeader('X-Public-Key', $projectWithOrigins->public_key)
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'missing_origin']);
    }

    #[Test]
    public function publicRouteRejectsDisallowedOrigin(): void
    {
        $projectWithOrigins = $this->createProjectWithOrigins(['https://app.example.com']);
        $charge = $this->createCharge($projectWithOrigins);

        $this->withHeader('X-Public-Key', $projectWithOrigins->public_key)
            ->withHeader('Origin', 'https://evil.com')
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'origin_not_allowed']);
    }

    #[Test]
    public function publicRouteAllowsExactOriginMatch(): void
    {
        $projectWithOrigins = $this->createProjectWithOrigins(['https://app.example.com']);
        $charge = $this->createCharge($projectWithOrigins);

        $this->withHeader('X-Public-Key', $projectWithOrigins->public_key)
            ->withHeader('Origin', 'https://app.example.com')
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(200);
    }

    #[Test]
    public function publicRouteAllowsWildcardSubdomainOrigin(): void
    {
        $projectWithOrigins = $this->createProjectWithOrigins(['*.example.com']);
        $charge = $this->createCharge($projectWithOrigins);

        $this->withHeader('X-Public-Key', $projectWithOrigins->public_key)
            ->withHeader('Origin', 'https://app.example.com')
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(200);
    }

    #[Test]
    public function publicRouteAllowsDoubleWildcardOrigin(): void
    {
        $projectWithOrigins = $this->createProjectWithOrigins(['*.*.example.com']);
        $charge = $this->createCharge($projectWithOrigins);

        $this->withHeader('X-Public-Key', $projectWithOrigins->public_key)
            ->withHeader('Origin', 'https://staging.app.example.com')
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(200);
    }

    #[Test]
    public function wildcardDoesNotMatchMultipleLevels(): void
    {
        $projectWithOrigins = $this->createProjectWithOrigins(['*.example.com']);
        $charge = $this->createCharge($projectWithOrigins);

        $this->withHeader('X-Public-Key', $projectWithOrigins->public_key)
            ->withHeader('Origin', 'https://staging.app.example.com')
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'origin_not_allowed']);
    }

    #[Test]
    public function noOriginCheckWhenAllowedOriginsIsEmpty(): void
    {
        $charge = $this->createCharge();

        $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function withPublicKey(): static
    {
        return $this->withHeader('X-Public-Key', $this->project->public_key);
    }

    private function createProject(): Project
    {
        $tokenGen = app(TokenGenerator::class);

        $project = Project::create([
            'name' => 'Test Project',
            'public_id' => $tokenGen->generateProjectId(),
            'api_key' => $tokenGen->generateApiKey(),
            'public_key' => $tokenGen->generatePublicKey(),
            'is_active' => true,
        ]);

        Balance::create(['project_id' => $project->id, 'available' => 0, 'pending' => 0]);

        return $project;
    }

    private function createProjectWithOrigins(array $origins): Project
    {
        $tokenGen = app(TokenGenerator::class);

        $project = Project::create([
            'name' => 'Test Project With Origins',
            'public_id' => $tokenGen->generateProjectId(),
            'api_key' => $tokenGen->generateApiKey(),
            'public_key' => $tokenGen->generatePublicKey(),
            'allowed_origins' => $origins,
            'is_active' => true,
        ]);

        Balance::create(['project_id' => $project->id, 'available' => 0, 'pending' => 0]);

        return $project;
    }

    private function createCharge(?Project $project = null): Charge
    {
        $tokenGen = app(TokenGenerator::class);
        $owner = $project ?? $this->project;

        return Charge::create([
            'project_id' => $owner->id,
            'public_id' => $tokenGen->generateChargeId(),
            'amount' => 100.00,
            'currency' => 'BRL',
            'description' => 'Test charge',
            'status' => 'pending',
        ]);
    }
}
