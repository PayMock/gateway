<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Balance
 */
final class BalanceController extends Controller
{
    /**
     * Get account balance.
     *
     * @operationId getBalance
     */
    public function show(Request $request): JsonResponse
    {
        $project = $request->get('_project');

        $balance = Balance::firstOrCreate(
            ['project_id' => $project->id],
            ['available' => 0, 'pending' => 0],
        );

        return response()->json([
            'object' => 'balance',
            'available' => [
                'amount' => (float) $balance->available,
                'currency' => 'BRL',
            ],
            'pending' => [
                'amount' => (float) $balance->pending,
                'currency' => 'BRL',
            ],
        ]);
    }
}
