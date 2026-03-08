<?php

namespace Tests\Feature\Api;

use App\Models\Balance;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\Security\TokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Redis to avoid needing a real Redis connection in tests
        Redis::shouldReceive('xadd')->byDefault()->andReturn('ok');

        $this->project = $this->createProject();
    }

    #[Test]
    public function canCreateAPayment(): void
    {
        $response = $this->withApiKey()
            ->postJson('/api/v1/payments', [
                'amount' => 100.00,
                'currency' => 'BRL',
                'method' => 'credit_card',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'object', 'amount', 'currency', 'status', 'method', 'created',
            ])
            ->assertJsonFragment(['object' => 'payment']);

        $data = $response->json();

        $this->assertStringStartsWith('pay_', $data['id']);
    }

    #[Test]
    public function fraud013AmountResultsInFraudStatus(): void
    {
        $response = $this->withApiKey()
            ->postJson('/api/v1/payments', [
                'amount' => 13.00,
                'method' => 'credit_card',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'fraud']);
    }

    #[Test]
    public function stolenCardResultsInFraudStatus(): void
    {
        $response = $this->withApiKey()
            ->postJson('/api/v1/payments', [
                'amount' => 100.00,
                'method' => 'credit_card',
                'card_number' => '4111111111110000',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'fraud']);
    }

    #[Test]
    public function lucky777AmountIsApproved(): void
    {
        $response = $this->withApiKey()
            ->postJson('/api/v1/payments', [
                'amount' => 777.00,
                'method' => 'credit_card',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'approved']);
    }

    #[Test]
    public function canForceSimulationViaHeader(): void
    {
        $response = $this->withApiKey()
            ->withHeader('X-PayMock-Rule', 'FRAUD_013')
            ->postJson('/api/v1/payments', [
                'amount' => 50.00,
                'method' => 'credit_card',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'fraud'])
            ->assertJsonFragment(['simulation_rule' => 'FRAUD_013']);
    }

    #[Test]
    public function idempotentPaymentReturnsSameTransaction(): void
    {
        $idempotencyKey = 'test-idem-key-' . uniqid();

        $first = $this->withApiKey()
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/payments', ['amount' => 100.00, 'method' => 'credit_card']);

        $second = $this->withApiKey()
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/payments', ['amount' => 100.00, 'method' => 'credit_card']);

        $this->assertEquals($first->json('id'), $second->json('id'));
    }

    #[Test]
    public function pixDuplicateWebhookSendsTwoWebhooks(): void
    {
        // Use a clean mock for this test
        Redis::shouldReceive('xadd')->times(2)->andReturn('ok');

        $this->withApiKey()
            ->postJson('/api/v1/payments', [
                'amount' => 50.77, // Rule PIX_DUPLICATE_WEBHOOK
                'method' => 'pix',
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['status' => 'approved']);
    }

    #[Test]
    public function canGetAPayment(): void
    {
        $createResponse = $this->withApiKey()
            ->postJson('/api/v1/payments', ['amount' => 100.00, 'method' => 'pix']);

        $paymentId = $createResponse->json('id');

        $getResponse = $this->withApiKey()
            ->getJson("/api/v1/payments/{$paymentId}");

        $getResponse->assertStatus(200)
            ->assertJsonFragment(['id' => $paymentId]);
    }

    #[Test]
    public function canListPaymentsWithPagination(): void
    {
        // Create a few payments
        for ($i = 0; $i < 3; $i++) {
            $this->withApiKey()->postJson('/api/v1/payments', [
                'amount' => 100.00,
                'method' => 'credit_card',
            ]);
        }

        $response = $this->withApiKey()
            ->getJson('/api/v1/payments?limit=2');

        $response->assertStatus(200)
            ->assertJsonStructure(['object', 'data', 'has_more'])
            ->assertJsonFragment(['object' => 'list']);

        $this->assertCount(2, $response->json('data'));
        $this->assertTrue($response->json('has_more'));
    }

    #[Test]
    public function canCancelAPayment(): void
    {
        $createResponse = $this->withApiKey()
            ->postJson('/api/v1/payments', ['amount' => 100.00, 'method' => 'credit_card']);

        // Create a pending transaction manually so we can cancel it
        $transaction = Transaction::where('public_id', $createResponse->json('id'))->first();
        $transaction->status = 'pending';
        $transaction->save();

        $cancelResponse = $this->withApiKey()
            ->postJson("/api/v1/payments/{$transaction->public_id}/cancel");

        $cancelResponse->assertStatus(200)
            ->assertJsonFragment(['status' => 'canceled']);
    }

    #[Test]
    public function cannotCancelApprovedPayment(): void
    {
        $createResponse = $this->withApiKey()
            ->postJson('/api/v1/payments', [
                'amount' => 777.00, // Lucky 777 → approved
                'method' => 'credit_card',
            ]);

        $paymentId = $createResponse->json('id');

        $this->withApiKey()
            ->postJson("/api/v1/payments/{$paymentId}/cancel")
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    #[Test]
    public function returns404ForUnknownPayment(): void
    {
        $this->withApiKey()
            ->getJson('/api/v1/payments/pay_nonexistent')
            ->assertStatus(404);
    }

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
            'is_active' => true,
        ]);

        Balance::create(['project_id' => $project->id, 'available' => 0, 'pending' => 0]);

        return $project;
    }
}
