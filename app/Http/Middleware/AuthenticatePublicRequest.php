<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Services\Security\OriginValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates public API requests using a public key.
 *
 * Public routes are intended for client-side use (browser, mobile).
 * They require:
 *   - Header: X-Public-Key: pk_test_xxx
 *   - Header: Origin: https://yourdomain.com  (validated against allowed_origins)
 *
 * If allowed_origins is empty/null, no origin restriction is applied.
 * Injects the resolved Project onto the request as '_project'.
 */
final class AuthenticatePublicRequest
{
    public function __construct(
        private readonly OriginValidator $originValidator,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $publicKey = $request->header('X-Public-Key');

        if ($publicKey === null) {
            return $this->errorMissingPublicKey();
        }

        $project = Project::query()
            ->where('public_key', $publicKey)
            ->where('is_active', true)
            ->first();

        if ($project === null) {
            return $this->errorInvalidPublicKey();
        }

        $allowedOrigins = $project->allowed_origins;

        // Only enforce origin check when allowed_origins is configured
        if (!empty($allowedOrigins)) {
            $origin = $request->header('Origin');

            if ($origin === null) {
                return $this->errorMissingOrigin();
            }

            $isOriginAllowed = $this->originValidator->isAllowed($origin, $allowedOrigins);

            if (!$isOriginAllowed) {
                return $this->errorOriginNotAllowed($origin);
            }
        }

        $request->merge(['_project' => $project]);

        return $next($request);
    }

    private function errorMissingPublicKey(): Response
    {
        return response()->json([
            'error' => [
                'type' => 'authentication_error',
                'code' => 'missing_public_key',
                'message' => 'No public key provided. Include it as: X-Public-Key: pk_test_xxx',
            ],
        ], 401);
    }

    private function errorInvalidPublicKey(): Response
    {
        return response()->json([
            'error' => [
                'type' => 'authentication_error',
                'code' => 'invalid_public_key',
                'message' => 'Invalid public key.',
            ],
        ], 401);
    }

    private function errorMissingOrigin(): Response
    {
        return response()->json([
            'error' => [
                'type' => 'authentication_error',
                'code' => 'missing_origin',
                'message' => 'This project requires an Origin header. Include the origin of your application.',
            ],
        ], 403);
    }

    private function errorOriginNotAllowed(string $origin): Response
    {
        return response()->json([
            'error' => [
                'type' => 'authentication_error',
                'code' => 'origin_not_allowed',
                'message' => "Origin '{$origin}' is not allowed for this project.",
            ],
        ], 403);
    }
}
