<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PayMock — Complete Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <span class="text-4xl">⚡</span>
            <h1 class="text-white text-2xl font-bold mt-2">PayMock Gateway</h1>
            <p class="text-slate-400 text-sm mt-1">Simulated Payment — Test Environment</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white">
                <p class="text-sm opacity-80">Payment amount</p>
                <p class="text-4xl font-bold mt-1">
                    {{ number_format($transaction->amount, 2, ',', '.') }}
                    <span class="text-lg font-medium">{{ $transaction->currency }}</span>
                </p>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Payment ID</span>
                    <span class="font-mono text-gray-800 text-xs">{{ $transaction->public_id }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Method</span>
                    <span class="font-medium text-gray-800">{{ strtoupper($transaction->method) }}</span>
                </div>
                @if($transaction->description)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Description</span>
                    <span class="text-gray-800">{{ $transaction->description }}</span>
                </div>
                @endif
            </div>

            <div class="px-6 pb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                    <p class="text-yellow-800 text-xs font-medium text-center">
                        🧪 This is a simulated payment — no real money is charged
                    </p>
                </div>

                <form method="POST" action="{{ route('payment.confirm', $token) }}">
                    @csrf
                    <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold py-4 px-6 rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                        Confirm Payment
                    </button>
                </form>

                <p class="text-center text-xs text-gray-400 mt-3">
                    PayMock Gateway &mdash; Open Source Payment Simulator
                </p>
            </div>
        </div>
    </div>
</body>
</html>
