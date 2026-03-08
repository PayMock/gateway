<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API requests using a Bearer API key.
 * Injects the authenticated Project onto the request.
 *
 * Header: Authorization: Bearer sk_test_xxx
 */
final class AuthenticateProject
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json([
                'error' => [
                    'type' => 'authentication_error',
                    'code' => 'missing_api_key',
                    'message' => 'No API key provided. Include it as: Authorization: Bearer sk_test_xxx',
                ],
            ], 401);
        }

        $project = Project::query()
            ->where('api_key', $token)
            ->where('is_active', true)
            ->first();

        if ($project === null) {
            return response()->json([
                'error' => [
                    'type' => 'authentication_error',
                    'code' => 'invalid_api_key',
                    'message' => 'Invalid API key.',
                ],
            ], 401);
        }

        $request->merge(['_project' => $project]);

        return $next($request);
    }
}
