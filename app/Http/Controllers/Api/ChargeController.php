<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Services\Charges\ChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Charges
 */
final class ChargeController extends Controller
{
    public function __construct(
        private readonly ChargeService $chargeService,
    ) {
    }

    /**
     * Create a charge.
     *
     * Creates a payment request (cobrança) to be paid by the customer
     * via the public API. Use the returned charge ID to redirect your
     * customer to the payment flow.
     *
     * @operationId createCharge
     */
    public function store(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|size:3',
            'description' => 'nullable|string|max:500',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'metadata' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $charge = $this->chargeService->create($project, $validated);

        return response()->json($this->formatCharge($charge), 201);
    }

    /**
     * Get a charge.
     *
     * @operationId getCharge
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $project = $request->get('_project');
        $charge = $this->findOrFail($project, $id);

        return response()->json($this->formatCharge($charge));
    }

    /**
     * List charges.
     *
     * @operationId listCharges
     */
    public function index(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        $limit = min((int) $request->query('limit', 20), 100);
        $status = $request->query('status');

        $query = Charge::query()
            ->where('project_id', $project->id)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        $startingAfter = $request->query('starting_after');

        if ($startingAfter !== null) {
            $pivot = Charge::where('public_id', $startingAfter)->first();

            if ($pivot !== null) {
                $query->where('created_at', '<', $pivot->created_at);
            }
        }

        $charges = $query->limit($limit + 1)->get();

        $hasMore = $charges->count() > $limit;

        $data = $charges->take($limit)->map(
            fn (Charge $charge) => $this->formatCharge($charge)
        );

        return response()->json([
            'object' => 'list',
            'data' => $data,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Cancel a charge.
     *
     * @operationId cancelCharge
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $project = $request->get('_project');
        $charge = $this->findOrFail($project, $id);

        if ($charge->status !== 'pending') {
            return response()->json([
                'error' => [
                    'type' => 'invalid_request',
                    'code' => 'charge_not_cancelable',
                    'message' => "Charge with status '{$charge->status}' cannot be canceled.",
                ],
            ], 422);
        }

        $charge->status = 'canceled';
        $charge->save();

        return response()->json($this->formatCharge($charge));
    }

    private function findOrFail(mixed $project, string $publicId): Charge
    {
        $charge = Charge::query()
            ->where('project_id', $project->id)
            ->where('public_id', $publicId)
            ->first();

        if ($charge === null) {
            abort(response()->json([
                'error' => [
                    'type' => 'invalid_request',
                    'code' => 'resource_not_found',
                    'message' => 'No such charge: ' . $publicId,
                ],
            ], 404));
        }

        return $charge;
    }

    private function formatCharge(Charge $charge): array
    {
        return [
            'id' => $charge->public_id,
            'object' => 'charge',
            'amount' => (float) $charge->amount,
            'currency' => $charge->currency,
            'status' => $charge->status,
            'description' => $charge->description,
            'customer_name' => $charge->customer_name,
            'customer_email' => $charge->customer_email,
            'expires_at' => $charge->expires_at?->timestamp,
            'created' => $charge->created_at->timestamp,
        ];
    }
}
