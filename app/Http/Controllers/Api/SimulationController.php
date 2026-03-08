<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use App\Simulation\Pipeline\RuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Simulation
 */
final class SimulationController extends Controller
{
    public function __construct(
        private readonly RuleRegistry $registry,
        private readonly PaymentService $paymentService,
    ) {
    }

    /**
     * List all simulation rules.
     *
     * Returns all available simulation rules with their identifiers and priorities.
     * Use these identifiers with the X-PayMock-Rule header to force specific outcomes.
     *
     * @operationId listSimulationRules
     */
    public function rules(): JsonResponse
    {
        $rules = $this->registry->all()->map(fn ($rule) => [
            'id' => $rule->identifier(),
            'priority' => $rule->priority(),
            'class' => class_basename($rule),
        ]);

        return response()->json([
            'object' => 'list',
            'data' => $rules,
        ]);
    }

    /**
     * Force a specific simulation scenario.
     *
     * Convenience endpoint to trigger a payment with a forced rule.
     * Equivalent to POST /v1/payments with X-PayMock-Rule header.
     *
     * @operationId forceSimulation
     */
    public function simulate(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        $validated = $request->validate([
            'rule' => 'required|string',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency' => 'sometimes|string|size:3',
            'method' => 'sometimes|in:credit_card,pix,qrcode,internal_balance',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email',
        ]);

        $data = [
            'amount' => $validated['amount'] ?? 100.00,
            'currency' => $validated['currency'] ?? 'BRL',
            'method' => $validated['method'] ?? 'credit_card',
            'customer_name' => $validated['customer_name'] ?? null,
            'forced_rule' => $validated['rule'],
        ];

        $transaction = $this->paymentService->createPayment($project, $data);

        return response()->json([
            'transaction_id' => $transaction->public_id,
            'status' => $transaction->status,
            'failure_reason' => $transaction->failure_reason,
            'simulation_rule' => $transaction->simulation_rule,
        ], 201);
    }
}
