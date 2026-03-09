<?php

namespace Tests\Unit\Security;

use App\Services\Security\OriginValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OriginValidatorTest extends TestCase
{
    private OriginValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new OriginValidator();
    }

    // -------------------------------------------------------------------------
    // Exact match
    // -------------------------------------------------------------------------

    #[Test]
    public function exactOriginIsAllowed(): void
    {
        $isAllowed = $this->validator->isAllowed(
            'https://app.example.com',
            ['https://app.example.com', 'app.example.com'],
        );

        $this->assertTrue($isAllowed);
    }

    #[Test]
    public function exactOriginMatchesHostOnly(): void
    {
        // Pattern 'app.example.com' should match host regardless of scheme
        $isAllowed = $this->validator->isAllowed(
            'https://app.example.com',
            ['app.example.com'],
        );

        $this->assertTrue($isAllowed);
    }

    #[Test]
    public function differentHostIsNotAllowed(): void
    {
        $isAllowed = $this->validator->isAllowed(
            'https://evil.com',
            ['https://app.example.com'],
        );

        $this->assertFalse($isAllowed);
    }

    // -------------------------------------------------------------------------
    // Single wildcard (*.domain.com)
    // -------------------------------------------------------------------------

    #[Test]
    public function singleWildcardMatchesOneSubdomainLevel(): void
    {
        $isAllowed = $this->validator->isAllowed(
            'https://app.example.com',
            ['*.example.com'],
        );

        $this->assertTrue($isAllowed);
    }

    #[Test]
    public function singleWildcardDoesNotMatchTwoSubdomainLevels(): void
    {
        $isAllowed = $this->validator->isAllowed(
            'https://staging.app.example.com',
            ['*.example.com'],
        );

        $this->assertFalse($isAllowed);
    }

    #[Test]
    public function singleWildcardDoesNotMatchBareRootDomain(): void
    {
        $isAllowed = $this->validator->isAllowed(
            'https://example.com',
            ['*.example.com'],
        );

        $this->assertFalse($isAllowed);
    }

    // -------------------------------------------------------------------------
    // Double wildcard (*.*.domain.com)
    // -------------------------------------------------------------------------

    #[Test]
    public function doubleWildcardMatchesTwoSubdomainLevels(): void
    {
        $isAllowed = $this->validator->isAllowed(
            'https://staging.app.example.com',
            ['*.*.example.com'],
        );

        $this->assertTrue($isAllowed);
    }

    #[Test]
    public function doubleWildcardDoesNotMatchOneSubdomainLevel(): void
    {
        $isAllowed = $this->validator->isAllowed(
            'https://app.example.com',
            ['*.*.example.com'],
        );

        $this->assertFalse($isAllowed);
    }

    // -------------------------------------------------------------------------
    // Empty / invalid inputs
    // -------------------------------------------------------------------------

    #[Test]
    public function emptyAllowedListReturnsFalse(): void
    {
        $isAllowed = $this->validator->isAllowed('https://app.example.com', []);

        $this->assertFalse($isAllowed);
    }

    #[Test]
    public function malformedOriginReturnsFalse(): void
    {
        $isAllowed = $this->validator->isAllowed('not-a-url', ['*.example.com']);

        $this->assertFalse($isAllowed);
    }

    // -------------------------------------------------------------------------
    // Multiple patterns
    // -------------------------------------------------------------------------

    #[Test]
    public function firstMatchingPatternGrantsAccess(): void
    {
        $isAllowed = $this->validator->isAllowed(
            'https://other.net',
            ['*.example.com', 'other.net'],
        );

        $this->assertTrue($isAllowed);
    }

    #[Test]
    public function noneMatchingPatternsDenyAccess(): void
    {
        $isAllowed = $this->validator->isAllowed(
            'https://hacker.io',
            ['*.example.com', 'app.mysite.com'],
        );

        $this->assertFalse($isAllowed);
    }

    // -------------------------------------------------------------------------
    // Port in origin
    // -------------------------------------------------------------------------

    #[Test]
    public function originWithPortMatchesHostWithoutPort(): void
    {
        // parse_url extracts host without port, pattern should match
        $isAllowed = $this->validator->isAllowed(
            'http://localhost:3000',
            ['localhost'],
        );

        $this->assertTrue($isAllowed);
    }
}
