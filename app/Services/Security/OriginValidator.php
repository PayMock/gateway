<?php

namespace App\Services\Security;

/**
 * Validates request origins against a list of allowed hosts.
 *
 * Supports wildcard patterns:
 *   - *.domain.com      → matches any single subdomain (e.g. app.domain.com)
 *   - *.*.domain.com    → matches two subdomain levels (e.g. x.app.domain.com)
 *   - domain.com        → exact match only
 */
final class OriginValidator
{
    /**
     * Returns true if the given origin is allowed by at least one pattern.
     *
     * @param string[] $allowedPatterns
     */
    public function isAllowed(string $origin, array $allowedPatterns): bool
    {
        $originHost = $this->extractHost($origin);

        if ($originHost === null) {
            return false;
        }

        foreach ($allowedPatterns as $pattern) {
            if ($this->matchesPattern($originHost, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts the host (without port) from a full origin URL.
     * Returns null if the origin is malformed.
     */
    private function extractHost(string $origin): ?string
    {
        $parsed = parse_url($origin);

        if (empty($parsed['host'])) {
            return null;
        }

        return strtolower($parsed['host']);
    }

    /**
     * Checks whether a host matches a single pattern.
     *
     * Patterns may be either host-only (*.domain.com) or full URLs
     * (https://app.domain.com). When a scheme is present, the host
     * is extracted from the pattern before comparison.
     *
     * Pattern rules:
     *   - No wildcard → exact host match (case-insensitive)
     *   - Wildcard (*) → each * matches exactly one label (segment between dots)
     */
    private function matchesPattern(string $host, string $pattern): bool
    {
        $pattern = strtolower(trim($pattern));

        // If the pattern looks like a URL (has a scheme), extract only the host
        if (str_contains($pattern, '://')) {
            $parsed = parse_url($pattern);
            $pattern = $parsed['host'] ?? $pattern;
        }

        // Exact match — fast path, no regex needed
        if (!str_contains($pattern, '*')) {
            return $host === $pattern;
        }

        // Convert the wildcard pattern into a regex
        // Each * matches one label: one or more chars that are not a dot
        $escaped = preg_quote($pattern, '/');
        $regex = '/^' . str_replace('\*', '[^.]+', $escaped) . '$/';

        return (bool) preg_match($regex, $host);
    }
}
