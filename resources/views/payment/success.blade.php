<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PayMock — Payment Successful</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-green-900 via-emerald-800 to-green-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md text-center">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-6xl mb-4">✅</div>
            <h1 class="text-2xl font-bold text-gray-900">Payment Confirmed!</h1>
            <p class="text-gray-500 mt-2">Your simulated payment has been processed successfully.</p>
            <div class="mt-6 bg-gray-50 rounded-xl p-4 text-left space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Amount</span>
                    <span class="font-semibold">{{ number_format($transaction->amount, 2, ',', '.') }} {{ $transaction->currency }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Status</span>
                    <span class="text-green-600 font-medium">Approved</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">ID</span>
                    <span class="font-mono text-xs">{{ $transaction->public_id }}</span>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-6">PayMock Gateway &mdash; Test Environment</p>
        </div>
    </div>
</body>
</html>
