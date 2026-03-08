<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\Project;
use App\Services\Security\TokenGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Projects
 */
final class ProjectController extends Controller
{
    public function __construct(
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    /**
     * Create a new project.
     *
     * Creates a new sandbox project and returns the API key.
     * Store the api_key securely — it will not be shown again in list responses.
     *
     * @operationId createProject
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'webhook_url' => 'nullable|url|max:500',
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'public_id' => $this->tokenGenerator->generateProjectId(),
            'api_key' => $this->tokenGenerator->generateApiKey(),
            'webhook_url' => $validated['webhook_url'] ?? null,
            'is_active' => true,
        ]);

        Balance::create([
            'project_id' => $project->id,
            'available' => 0,
            'pending' => 0,
        ]);

        return response()->json([
            'id' => $project->public_id,
            'object' => 'project',
            'name' => $project->name,
            'api_key' => $project->api_key,
            'webhook_url' => $project->webhook_url,
            'created' => $project->created_at->timestamp,
        ], 201);
    }

    /**
     * Get a project.
     *
     * @operationId getProject
     */
    public function show(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        return response()->json([
            'id' => $project->public_id,
            'object' => 'project',
            'name' => $project->name,
            'webhook_url' => $project->webhook_url,
            'created' => $project->created_at->timestamp,
        ]);
    }
}
