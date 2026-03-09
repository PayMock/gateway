<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\Balances\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    public function __construct(
        protected BalanceService $balanceService
    ) {
    }

    /**
     * List payouts for the project.
     */
    public function index(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        $payouts = Payout::where('project_id', $project->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'object' => 'list',
            'url' => '/v1/payouts',
            'has_more' => $payouts->hasMorePages(),
            'data' => $payouts->items(),
        ]);
    }

    /**
     * Create a new payout request.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:5.00', // Minimum withdrawal 5.00
            'transfer_details' => 'required|array',
            'transfer_details.type' => 'required|in:pix,bank_account',
            'transfer_details.pix_key' => 'required_if:transfer_details.type,pix|string',
            'transfer_details.key_type' => 'required_if:transfer_details.type,pix|in:cpf,cnpj,email,phone,random',
            'transfer_details.bank' => 'required_if:transfer_details.type,bank_account|string',
            'transfer_details.agency' => 'required_if:transfer_details.type,bank_account|string',
            'transfer_details.account' => 'required_if:transfer_details.type,bank_account|string',
        ]);

        $project = $request->get('_project');
        $balance = $this->balanceService->getOrCreateBalance($project);

        if ($balance->available < $request->amount) {
            return response()->json([
                'error' => [
                    'type' => 'invalid_request_error',
                    'message' => 'Insufficient available balance for withdrawal.',
                ]
            ], 400);
        }

        return DB::transaction(function () use ($project, $request) {
            // 1. Create Payout Record
            $payout = Payout::create([
                'project_id' => $project->id,
                'amount' => $request->amount,
                'status' => 'requested',
                'transfer_details' => $request->transfer_details,
            ]);

            // 2. Debit from Available Balance via Ledger
            $this->balanceService->debit(
                $project,
                (float) $request->amount,
                'available',
                "Withdrawal Request",
                $payout
            );

            // 3. Increment Withdrawn Total
            $this->balanceService->getOrCreateBalance($project)->increment('withdrawn', $request->amount);

            // 4. Dispatch Payout Simulation Job
            $type = $request->transfer_details['type'] ?? 'pix';
            $delay = $type === 'pix' ? 1 : 60; // 1s for PIX (near instant), 1m for Bank
            \App\Jobs\ProcessPayout::dispatch($payout)->delay(now()->addSeconds($delay));

            return response()->json($payout, 201);
        });
    }

    /**
     * Get a specific payout.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $project = $request->get('_project');

        $payout = Payout::where('project_id', $project->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($payout);
    }

    /**
     * Manually confirm a payout (Simulation).
     */
    public function confirm(Request $request, string $id): JsonResponse
    {
        $project = $request->get('_project');
        $payout = Payout::where('project_id', $project->id)
            ->where('id', $id)
            ->where('status', 'requested')
            ->firstOrFail();

        $payout->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        app(\App\Services\Webhooks\WebhookDispatcher::class)->dispatch($payout, 'payout.updated');

        return response()->json($payout);
    }

    /**
     * Manually fail a payout (Simulation).
     */
    public function fail(Request $request, string $id): JsonResponse
    {
        $project = $request->get('_project');
        $payout = Payout::where('project_id', $project->id)
            ->where('id', $id)
            ->where('status', 'requested')
            ->firstOrFail();

        DB::transaction(function () use ($project, $payout) {
            $payout->update(['status' => 'failed']);

            // Return funds to available balance
            $this->balanceService->credit(
                $project,
                (float) $payout->amount,
                'available',
                "Payout Failed: {$payout->id}",
                $payout
            );

            // Decrement withdrawn total
            $this->balanceService->getOrCreateBalance($project)->decrement('withdrawn', $payout->amount);
        });

        app(\App\Services\Webhooks\WebhookDispatcher::class)->dispatch($payout, 'payout.updated');

        return response()->json($payout);
    }
}
