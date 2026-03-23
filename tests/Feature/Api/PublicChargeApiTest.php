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

final class PublicChargeApiTest extends TestCase
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
    // PIX payment
    // -------------------------------------------------------------------------

    #[Test]
    public function canPayChargeWithPix(): void
    {
        $charge = $this->createCharge(50.00);

        $response = $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id', 'object', 'status', 'method', 'amount', 'currency',
                'payment' => ['transaction_id', 'qr_code_url', 'qr_code_base64', 'qr_code_mime'],
            ])
            ->assertJsonFragment([
                'method' => 'pix',
                'status' => 'pending',
            ]);

        // PIX must NOT auto-approve — it stays pending until confirmed
        $this->assertEquals('pending', $response->json('status'));

        // QR code URL must be present and non-empty
        $this->assertNotEmpty($response->json('payment.qr_code_url'));
        $this->assertNotEmpty($response->json('payment.qr_code_base64'));
        $this->assertEquals('image/svg+xml', $response->json('payment.qr_code_mime'));
    }

    #[Test]
    public function pixQrCodeBase64IsValidBase64(): void
    {
        $charge = $this->createCharge(75.00);

        $response = $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ]);

        $base64 = $response->json('payment.qr_code_base64');

        // Must decode without error
        $decoded = base64_decode($base64, strict: true);

        $this->assertNotFalse($decoded);

        // Decoded content must be an SVG
        $this->assertStringContainsString('<svg', $decoded);
    }

    #[Test]
    public function pixChargeStatusRemainsAfterPayment(): void
    {
        $charge = $this->createCharge(50.00);

        $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ]);

        // Charge should still be pending — not paid until QR confirmation
        $charge->refresh();
        $this->assertEquals('pending', $charge->status);
    }

    // -------------------------------------------------------------------------
    // Credit card payment
    // -------------------------------------------------------------------------

    #[Test]
    public function canPayChargeWithValidCard(): void
    {
        $charge = $this->createCharge(100.00);

        $response = $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'credit_card',
                'card_number' => '4111111111111111',
                'card_holder_name' => 'Alice Smith',
                'card_expiry' => '12/28',
                'card_cvv' => '123',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id', 'object', 'status', 'method',
                'payment' => ['transaction_id', 'status', 'failure_reason'],
            ])
            ->assertJsonFragment(['method' => 'credit_card']);
    }

    #[Test]
    public function cardPaymentWithLucky777ApprovesCharge(): void
    {
        $charge = $this->createCharge(777.00);

        $response = $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'credit_card',
                'card_number' => '4111111111111111',
                'card_holder_name' => 'Alice Smith',
                'card_expiry' => '12/28',
                'card_cvv' => '123',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'paid'])
            ->assertJsonFragment(['status' => 'approved']);

        $charge->refresh();
        $this->assertEquals('paid', $charge->status);
    }

    #[Test]
    public function stolenCardResultsInFraudAndChargeRemainsUnpaid(): void
    {
        $charge = $this->createCharge(100.00);

        $response = $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'credit_card',
                'card_number' => '4111111111110000', // ends 0000 → stolen
                'card_holder_name' => 'Bad Actor',
                'card_expiry' => '01/30',
                'card_cvv' => '999',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'fraud']);

        $charge->refresh();
        $this->assertEquals('pending', $charge->status);
    }

    #[Test]
    public function stolenCardResultsInFraudAndChargeRemainsUnpaidEndsWith1313(): void
    {
        $charge = $this->createCharge(100.00);

        $response = $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'credit_card',
                'card_number' => '4111111111111313', // ends 1313 → stolen
                'card_holder_name' => 'Bad Actor',
                'card_expiry' => '01/30',
                'card_cvv' => '999',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'fraud']);

        $charge->refresh();
        $this->assertEquals('pending', $charge->status);
    }

    #[Test]
    public function cardPaymentRequiresAllCardFields(): void
    {
        $charge = $this->createCharge(50.00);

        $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'credit_card',
                'card_number' => '4111111111111111',
                // missing card_holder_name, card_expiry, card_cvv
            ])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Guard clauses — charge state
    // -------------------------------------------------------------------------

    #[Test]
    public function cannotPayAlreadyPaidCharge(): void
    {
        $charge = $this->createCharge(100.00);
        $charge->status = 'paid';
        $charge->save();

        $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'charge_already_processed']);
    }

    #[Test]
    public function cannotPayCanceledCharge(): void
    {
        $charge = $this->createCharge(100.00);
        $charge->status = 'canceled';
        $charge->save();

        $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'charge_already_processed']);
    }

    #[Test]
    public function returns404ForUnknownCharge(): void
    {
        $this->withPublicKey()
            ->postJson('/api/v1/public/charges/chg_nonexistent/pay', [
                'method' => 'pix',
            ])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Status polling
    // -------------------------------------------------------------------------

    #[Test]
    public function canPollChargeStatus(): void
    {
        $charge = $this->createCharge(100.00);

        $this->withPublicKey()
            ->getJson("/api/v1/public/charges/{$charge->public_id}/status")
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $charge->public_id,
                'status' => 'pending',
            ]);
    }

    // -------------------------------------------------------------------------
    // QR code retrieval
    // -------------------------------------------------------------------------

    #[Test]
    public function canFetchQrCodeAfterPixPayment(): void
    {
        $charge = $this->createCharge(50.00);

        $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ]);

        $this->withPublicKey()
            ->get("/api/v1/public/charges/{$charge->public_id}/qrcode")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    #[Test]
    public function qrCodeReturns404BeforePixPayment(): void
    {
        $charge = $this->createCharge(50.00);

        $this->withPublicKey()
            ->getJson("/api/v1/public/charges/{$charge->public_id}/qrcode")
            ->assertStatus(404)
            ->assertJsonFragment(['code' => 'no_qr_code']);
    }

    // -------------------------------------------------------------------------
    // Isolation
    // -------------------------------------------------------------------------

    #[Test]
    public function cannotAccessAnotherProjectsCharge(): void
    {
        $other = $this->createProject();
        $charge = $this->createCharge(100.00, $other);

        $this->withPublicKey()
            ->postJson("/api/v1/public/charges/{$charge->public_id}/pay", [
                'method' => 'pix',
            ])
            ->assertStatus(404);
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

    private function createCharge(float $amount = 100.00, ?Project $project = null): Charge
    {
        $tokenGen = app(TokenGenerator::class);
        $owner = $project ?? $this->project;

        return Charge::create([
            'project_id' => $owner->id,
            'public_id' => $tokenGen->generateChargeId(),
            'amount' => $amount,
            'currency' => 'BRL',
            'description' => 'Test charge',
            'status' => 'pending',
        ]);
    }
}
