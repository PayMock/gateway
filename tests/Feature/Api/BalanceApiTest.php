<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Balance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceApiTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $this->apiKey = $this->project->api_key;
    }

    public function testCanGetBalanceSummary(): void
    {
        // 1. Create an approved payment to generate some pending balance
        $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->postJson('/api/v1/payments', [
                'amount' => 100.00,
                'method' => 'pix',
                'forced_rule' => 'PIX_APPROVED_00' // Force approval
            ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->getJson('/api/v1/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'object',
                'available',
                'pending',
                'withdrawn'
            ])
            ->assertJsonFragment(['amount' => 100, 'currency' => 'brl']);
    }

    public function testCanViewBalanceHistory(): void
    {
        // 1. Create a payment
        $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->postJson('/api/v1/payments', [
                'amount' => 50.00,
                'method' => 'pix',
                'forced_rule' => 'PIX_APPROVED_00'
            ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->getJson('/api/v1/balance/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'object',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'balance_type',
                        'amount',
                        'description',
                        'source_type',
                        'source_id'
                    ]
                ]
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['amount' => "50.00", 'type' => 'credit', 'balance_type' => 'pending']);
    }

    public function testCanRequestAnticipation(): void
    {
        // 1. Create a payment (funded in pending)
        $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->postJson('/api/v1/payments', [
                'amount' => 1000.00,
                'method' => 'pix',
                'forced_rule' => 'PIX_APPROVED_00'
            ]);

        // 2. Request advance (instant - 10% fee)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->postJson('/api/v1/balance/advance', [
                'amount' => 500.00,
                'days' => 0 // Instant
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'original_amount' => 500.00,
                'fee_percentage' => 10.0,
                'fee_amount' => 50.00,
                'net_amount' => 450.00
            ]);

        // 3. Check balance
        $summary = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->getJson('/api/v1/balance');

        $summary->assertJsonFragment(['amount' => 500, 'currency' => 'brl']); // pending
        $summary->assertJsonFragment(['amount' => 450, 'currency' => 'brl']); // available
    }

    public function testCanRequestPayout(): void
    {
        // 1. Setup available balance (via advance)
        $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->postJson('/api/v1/payments', [
                'amount' => 1000.00,
                'method' => 'pix',
                'forced_rule' => 'PIX_APPROVED_00'
            ]);
        $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->postJson('/api/v1/balance/advance', ['amount' => 1000, 'days' => 30]); // 0% fee

        // 2. Request payout
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->postJson('/api/v1/payouts', [
                'amount' => 100.00,
                'bank_details' => [
                    'bank' => 'PayMock Bank',
                    'agency' => '0001',
                    'account' => '12345-6'
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'amount' => 100.00,
                'status' => 'requested'
            ]);

        // 3. Verify balance
        $summary = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->getJson('/api/v1/balance');

        $summary->assertJsonFragment(['amount' => 900, 'currency' => 'brl']); // available (1000 - 100)
        $summary->assertJsonFragment(['amount' => 100, 'currency' => 'brl']); // withdrawn
    }
    public function testCannotAdvanceMoreThanPending(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->postJson('/api/v1/balance/advance', [
                'amount' => 100.00,
                'days' => 0
            ]);

        $response->assertStatus(400); // Caught by controller
    }

    public function testCannotPayoutMoreThanAvailable(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->postJson('/api/v1/payouts', [
                'amount' => 100.00,
                'bank_details' => ['pix_key' => 'test@test.com']
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Insufficient available balance for withdrawal.']);
    }
}
