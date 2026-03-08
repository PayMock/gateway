<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\Security\TokenGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @tags Webhooks
 */
final class WebhookController extends Controller
{
    public function __construct(
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    /**
     * Register a webhook endpoint.
     *
     * @operationId createWebhook
     */
    public function store(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        $validated = $request->validate([
            'url' => 'required|url|max:500',
        ]);

        $secret = Str::random(32);

        $webhook = Webhook::create([
            'project_id' => $project->id,
            'public_id' => $this->tokenGenerator->generateWebhookId(),
            'url' => $validated['url'],
            'secret' => $secret,
            'is_active' => true,
        ]);

        // Update project webhook_url for convenience
        $project->webhook_url = $validated['url'];
        $project->save();

        return response()->json([
            'id' => $webhook->public_id,
            'object' => 'webhook',
            'url' => $webhook->url,
            'secret' => $secret,         // Only shown once on creation
            'created' => $webhook->created_at->timestamp,
        ], 201);
    }

    /**
     * List webhooks.
     *
     * @operationId listWebhooks
     */
    public function index(Request $request): JsonResponse
    {
        $project = $request->get('_project');
        $webhooks = Webhook::where('project_id', $project->id)->get();

        $data = $webhooks->map(fn (Webhook $w) => [
            'id' => $w->public_id,
            'object' => 'webhook',
            'url' => $w->url,
            'created' => $w->created_at->timestamp,
        ]);

        return response()->json([
            'object' => 'list',
            'data' => $data,
            'has_more' => false,
        ]);
    }
}
