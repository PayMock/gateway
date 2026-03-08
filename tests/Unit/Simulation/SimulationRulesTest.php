<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Rules\Amount\FraudAmount666Rule;
use App\Simulation\Rules\Amount\FraudAmountRule;
use App\Simulation\Rules\Amount\Lucky777Rule;
use App\Simulation\Rules\Amount\SlowProcessingRule;
use App\Simulation\Rules\Amount\Timeout999Rule;
use App\Simulation\Rules\Amount\ZeroAmountRule;
use App\Simulation\Rules\Card\CardStolenRule;
use App\Simulation\Rules\Card\GatewayDownRule;
use App\Simulation\Rules\Card\InvalidCvvRule;
use App\Simulation\Rules\Card\IssuerUnavailableRule;
use App\Simulation\Rules\Pix\PixApprovedRule;
use App\Simulation\Rules\Pix\PixDuplicateWebhookRule;
use App\Simulation\Rules\Pix\PixFraudRule;
use App\Simulation\Rules\Time\Friday13Rule;
use App\Simulation\Rules\Time\MaintenanceWindowRule;
use App\Simulation\Rules\User\AdminBlockedRule;
use App\Simulation\Rules\User\TestEmailAutoApproveRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SimulationRulesTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Card Rules
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function cardStolenRuleMatchesCardEnding0000(): void
    {
        $context = $this->makeContext(amount: 100, method: 'credit_card', cardNumber: '4111111111110000');
        $rule = new CardStolenRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('fraud', $decision->status);
        $this->assertEquals('stolen_card', $decision->reason);
    }

    #[Test]
    public function cardStolenRuleDoesNotMatchOtherCards(): void
    {
        $context = $this->makeContext(amount: 100, method: 'credit_card', cardNumber: '4111111111111234');
        $rule = new CardStolenRule();

        $this->assertFalse($rule->matches($context));
    }

    #[Test]
    public function invalidCvvRuleMatchesCardEnding1234(): void
    {
        $context = $this->makeContext(amount: 100, method: 'credit_card', cardNumber: '4111111111111234');
        $rule = new InvalidCvvRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('failed', $decision->status);
        $this->assertEquals('invalid_cvv', $decision->reason);
    }

    #[Test]
    public function issuerUnavailableRuleMatchesCardEnding8888(): void
    {
        $context = $this->makeContext(amount: 100, method: 'credit_card', cardNumber: '4111111111118888');
        $rule = new IssuerUnavailableRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('failed', $decision->status);
        $this->assertEquals('issuer_unavailable', $decision->reason);
        $this->assertGreaterThan(0, $decision->delayMs);
    }

    #[Test]
    public function gatewayDownRuleMatchesCardEnding9999(): void
    {
        $context = $this->makeContext(amount: 100, method: 'credit_card', cardNumber: '4111111111119999');
        $rule = new GatewayDownRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('failed', $decision->status);
        $this->assertEquals('gateway_unavailable', $decision->reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Amount Rules
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('fraudAmountProvider')]
    public function fraud013RuleDetectsAmountsContaining13(float $amount): void
    {
        $context = $this->makeContext(amount: $amount, method: 'credit_card');
        $rule = new FraudAmountRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('fraud', $decision->status);
        $this->assertEquals('FRAUD_013', $decision->rule);
    }

    public static function fraudAmountProvider(): array
    {
        return [
            'amount is 13' => [13.00],
            'amount contains 13' => [100.13],
            'amount starts with 13' => [130.00],
            'amount is 1300' => [1300.00],
        ];
    }

    #[Test]
    public function fraud013DoesNotMatchSafeAmounts(): void
    {
        $context = $this->makeContext(amount: 99.99, method: 'credit_card');
        $rule = new FraudAmountRule();

        $this->assertFalse($rule->matches($context));
    }

    #[Test]
    public function fraud666RuleMatchesExactAmount(): void
    {
        $context = $this->makeContext(amount: 666.00, method: 'credit_card');
        $rule = new FraudAmount666Rule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('fraud', $decision->status);
    }

    #[Test]
    public function zeroAmountRuleRejectsZero(): void
    {
        $context = $this->makeContext(amount: 0, method: 'credit_card');
        $rule = new ZeroAmountRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('failed', $decision->status);
        $this->assertEquals('invalid_amount', $decision->reason);
    }

    #[Test]
    public function lucky777RuleApproves(): void
    {
        $context = $this->makeContext(amount: 777.00, method: 'credit_card');
        $rule = new Lucky777Rule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('approved', $decision->status);
    }

    #[Test]
    public function timeout999RuleFailsWithDelay(): void
    {
        $context = $this->makeContext(amount: 999.00, method: 'credit_card');
        $rule = new Timeout999Rule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('failed', $decision->status);
        $this->assertEquals('issuer_timeout', $decision->reason);
        $this->assertGreaterThan(0, $decision->delayMs);
    }

    #[Test]
    public function slowProcessingRuleReturnsProcessingStatus(): void
    {
        $context = $this->makeContext(amount: 1.23, method: 'credit_card');
        $rule = new SlowProcessingRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('processing', $decision->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PIX Rules
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function pixFraudRuleMatchesPixEnding013(): void
    {
        $context = $this->makeContext(amount: 50.13, method: 'pix');
        $rule = new PixFraudRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('fraud', $decision->status);
    }

    #[Test]
    public function pixFraudRuleDoesNotMatchCreditCard(): void
    {
        $context = $this->makeContext(amount: 50.13, method: 'credit_card');
        $rule = new PixFraudRule();

        $this->assertFalse($rule->matches($context));
    }

    #[Test]
    public function pixApprovedRuleApprovesPixEnding00(): void
    {
        $context = $this->makeContext(amount: 100.00, method: 'pix');
        $rule = new PixApprovedRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('approved', $decision->status);
    }

    #[Test]
    public function pixDuplicateWebhookRuleTriggersSideEffect(): void
    {
        $context = $this->makeContext(amount: 50.77, method: 'pix');
        $rule = new PixDuplicateWebhookRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('approved', $decision->status);
        $this->assertTrue($decision->hasSideEffect('duplicate_webhook'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Time Rules
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function maintenanceWindowRuleTriggersBetweenMidnightAnd0005(): void
    {
        $context = $this->makeContext(
            amount: 100,
            method: 'credit_card',
            timestamp: new \DateTimeImmutable('2026-03-08 00:03:00'),
        );
        $rule = new MaintenanceWindowRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('failed', $decision->status);
        $this->assertEquals('gateway_maintenance', $decision->reason);
    }

    #[Test]
    public function maintenanceWindowRuleDoesNotTriggerRegularHours(): void
    {
        $context = $this->makeContext(
            amount: 100,
            method: 'credit_card',
            timestamp: new \DateTimeImmutable('2026-03-08 10:00:00'),
        );

        $this->assertFalse((new MaintenanceWindowRule())->matches($context));
    }

    #[Test]
    public function friday13RuleTriggersOnFridayTheThirteenth(): void
    {
        // March 13, 2026 is a Friday
        $context = $this->makeContext(
            amount: 100,
            method: 'credit_card',
            timestamp: new \DateTimeImmutable('2026-03-13 12:00:00'),
        );
        $rule = new Friday13Rule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('pending', $decision->status);
        $this->assertEquals('manual_review_required', $decision->reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // User Rules
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function adminBlockedRuleBlocksCustomerNamedAdmin(): void
    {
        $context = $this->makeContext(amount: 100, method: 'credit_card', customerName: 'admin');
        $rule = new AdminBlockedRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('failed', $decision->status);
        $this->assertEquals('customer_blocked', $decision->reason);
    }

    #[Test]
    public function adminBlockedRuleIsCaseInsensitive(): void
    {
        $context = $this->makeContext(amount: 100, method: 'credit_card', customerName: 'ADMIN');
        $rule = new AdminBlockedRule();

        $this->assertTrue($rule->matches($context));
    }

    #[Test]
    public function testEmailAutoApproveRuleApprovesTestEmail(): void
    {
        $context = $this->makeContext(
            amount: 100,
            method: 'credit_card',
            customerEmail: 'john.test@example.com',
        );
        $rule = new TestEmailAutoApproveRule();

        $this->assertTrue($rule->matches($context));

        $decision = $rule->decide($context);

        $this->assertEquals('approved', $decision->status);
    }

    #[Test]
    public function testEmailRuleDoesNotMatchNormalEmails(): void
    {
        $context = $this->makeContext(
            amount: 100,
            method: 'credit_card',
            customerEmail: 'john@example.com',
        );

        $this->assertFalse((new TestEmailAutoApproveRule())->matches($context));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeContext(
        float $amount,
        string $method,
        ?string $cardNumber = null,
        ?string $customerName = null,
        ?string $customerEmail = null,
        ?\DateTimeImmutable $timestamp = null,
    ): SimulationContext {
        return new SimulationContext(
            transactionId: 'test-' . uniqid(),
            amount: $amount,
            currency: 'BRL',
            method: $method,
            cardNumber: $cardNumber,
            customerName: $customerName,
            customerEmail: $customerEmail,
            timestamp: $timestamp ?? new \DateTimeImmutable('2026-03-08 10:00:00'),
        );
    }
}
