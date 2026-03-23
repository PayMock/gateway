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

final class ChargeApiTest extends TestCase
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
    // Charge creation
    // -------------------------------------------------------------------------

    #[Test]
    public function canCreateACharge(): void
    {
        $response = $this->withApiKey()
            ->postJson('/api/v1/charges', [
                'amount' => 150.00,
                'currency' => 'BRL',
                'description' => 'Order #1001',
                'customer_name' => 'Alice',
                'customer_email' => 'alice@example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'object', 'amount', 'currency', 'status', 'created'])
            ->assertJsonFragment([
                'object' => 'charge',
                'status' => 'pending',
                'amount' => 150.00,
            ]);

        $this->assertStringStartsWith('chg_', $response->json('id'));
    }

    #[Test]
    public function chargeCreationRequiresAmount(): void
    {
        $this->withApiKey()
            ->postJson('/api/v1/charges', ['description' => 'Missing amount'])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Charge retrieval
    // -------------------------------------------------------------------------

    #[Test]
    public function canGetACharge(): void
    {
        $charge = $this->createCharge();

        $this->withApiKey()
            ->getJson("/api/v1/charges/{$charge->public_id}")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $charge->public_id, 'status' => 'pending']);
    }

    #[Test]
    public function returns404ForUnknownCharge(): void
    {
        $this->withApiKey()
            ->getJson('/api/v1/charges/chg_nonexistent')
            ->assertStatus(404);
    }

    #[Test]
    public function cannotAccessAnotherProjectsCharge(): void
    {
        $other = $this->createProject();
        $charge = $this->createCharge($other);

        $this->withApiKey()
            ->getJson("/api/v1/charges/{$charge->public_id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Charge listing
    // -------------------------------------------------------------------------

    #[Test]
    public function canListCharges(): void
    {
        $this->createCharge();
        $this->createCharge();

        $this->withApiKey()
            ->getJson('/api/v1/charges')
            ->assertStatus(200)
            ->assertJsonStructure(['object', 'data', 'has_more'])
            ->assertJsonFragment(['object' => 'list']);
    }

    #[Test]
    public function canFilterChargesByStatus(): void
    {
        $this->createCharge();

        $paid = $this->createCharge();
        $paid->status = 'paid';
        $paid->save();

        $response = $this->withApiKey()
            ->getJson('/api/v1/charges?status=paid');

        $response->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Charge cancellation
    // -------------------------------------------------------------------------

    #[Test]
    public function canCancelACharge(): void
    {
        $charge = $this->createCharge();

        $this->withApiKey()
            ->postJson("/api/v1/charges/{$charge->public_id}/cancel")
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'canceled']);
    }

    #[Test]
    public function cannotCancelAPaidCharge(): void
    {
        $charge = $this->createCharge();
        $charge->status = 'paid';
        $charge->save();

        $this->withApiKey()
            ->postJson("/api/v1/charges/{$charge->public_id}/cancel")
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'charge_not_cancelable']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function withApiKey(): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->project->api_key);
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
