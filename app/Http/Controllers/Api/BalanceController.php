<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Balances\BalanceService;
use App\Services\Balances\AdvanceService;
use App\Models\BalanceTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BalanceController extends Controller
{
    public function __construct(
        protected BalanceService $balanceService,
        protected AdvanceService $advanceService
    ) {
    }

    /**
     * Get a summary of the project's balances.
     */
    public function index(Request $request): JsonResponse
    {
        $project = $request->get('_project');
        $balance = $this->balanceService->getOrCreateBalance($project);

        return response()->json([
            'object' => 'balance',
            'available' => [
                ['amount' => (float) $balance->available, 'currency' => 'brl']
            ],
            'pending' => [
                ['amount' => (float) $balance->pending, 'currency' => 'brl']
            ],
            'withdrawn' => [
                ['amount' => (float) $balance->withdrawn, 'currency' => 'brl']
            ],
        ]);
    }

    /**
     * Get a list of balance transactions (ledger).
     */
    public function history(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        $transactions = BalanceTransaction::where('project_id', $project->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'object' => 'list',
            'url' => '/v1/balance/history',
            'has_more' => $transactions->hasMorePages(),
            'data' => $transactions->items(),
        ]);
    }

    /**
     * List available advance options/fees.
     */
    public function advanceOptions(): JsonResponse
    {
        return response()->json([
            'object' => 'list',
            'data' => $this->advanceService->getAdvanceOptions(),
        ]);
    }

    /**
     * Request an anticipation of pending balance.
     */
    public function requestAdvance(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'days' => 'required|numeric',
        ]);

        $project = $request->get('_project');

        try {
            $result = $this->advanceService->advance(
                $project,
                (float) $request->amount,
                (int) $request->days
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'type' => 'invalid_request_error',
                    'message' => $e->getMessage(),
                ]
            ], 400);
        }
    }
}
