<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Payments
 */
final class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    /**
     * Create a payment.
     *
     * Initiates a new payment and runs it through the simulation engine.
     * Use X-PayMock-Rule header to force a specific simulation result.
     *
     * @operationId createPayment
     */
    public function store(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|size:3',
            'method' => 'required|in:credit_card,pix,qrcode,internal_balance',
            'description' => 'nullable|string|max:500',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'card_number' => 'required_if:method,credit_card|string|min:13|max:19',
            'metadata' => 'nullable|array',
        ]);

        $validated['idempotency_key'] = $request->header('Idempotency-Key');
        $validated['forced_rule'] = $request->header('X-PayMock-Rule');

        $transaction = $this->paymentService->createPayment($project, $validated);

        return response()->json(
            $this->formatTransaction($transaction),
            201,
        );
    }

    /**
     * Get a payment.
     *
     * @operationId getPayment
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $project = $request->get('_project');
        $transaction = $this->findOrFail($project, $id);

        return response()->json($this->formatTransaction($transaction));
    }

    /**
     * List payments.
     *
     * @operationId listPayments
     */
    public function index(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        $limit = min((int) $request->query('limit', 20), 100);
        $status = $request->query('status');

        $query = Transaction::query()
            ->where('project_id', $project->id)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        $startingAfter = $request->query('starting_after');

        if ($startingAfter !== null) {
            $pivot = Transaction::where('public_id', $startingAfter)->first();

            if ($pivot) {
                $query->where('created_at', '<', $pivot->created_at);
            }
        }

        $transactions = $query->limit($limit + 1)->get();

        $hasMore = $transactions->count() > $limit;

        $data = $transactions->take($limit)->map(
            fn (Transaction $t) => $this->formatTransaction($t)
        );

        return response()->json([
            'object' => 'list',
            'data' => $data,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Cancel a payment.
     *
     * @operationId cancelPayment
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $project = $request->get('_project');
        $transaction = $this->findOrFail($project, $id);

        if ($transaction->isTerminal()) {
            return response()->json([
                'error' => [
                    'type' => 'invalid_request',
                    'code' => 'payment_not_cancelable',
                    'message' => "Payment with status '{$transaction->status}' cannot be canceled.",
                ],
            ], 422);
        }

        $transaction->status = 'canceled';
        $transaction->save();

        return response()->json($this->formatTransaction($transaction));
    }

    private function findOrFail(mixed $project, string $publicId): Transaction
    {
        $transaction = Transaction::query()
            ->where('project_id', $project->id)
            ->where('public_id', $publicId)
            ->first();

        if ($transaction === null) {
            abort(response()->json([
                'error' => [
                    'type' => 'invalid_request',
                    'code' => 'resource_not_found',
                    'message' => 'No such payment: ' . $publicId,
                ],
            ], 404));
        }

        return $transaction;
    }

    private function formatTransaction(Transaction $transaction): array
    {
        $data = [
            'id' => $transaction->public_id,
            'object' => 'payment',
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'method' => $transaction->method,
            'description' => $transaction->description,
            'customer_name' => $transaction->customer_name,
            'customer_email' => $transaction->customer_email,
            'failure_reason' => $transaction->failure_reason,
            'simulation_rule' => $transaction->simulation_rule,
            'created' => $transaction->created_at->timestamp,
        ];

        if ($transaction->qr_code_url) {
            $data['pix'] = [
                'qr_code_url' => $transaction->qr_code_url,
            ];
        }

        return $data;
    }
}
