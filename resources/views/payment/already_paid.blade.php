<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PayMock — Already Paid</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-900 via-indigo-800 to-blue-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md text-center">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-6xl mb-4">🔁</div>
            <h1 class="text-2xl font-bold text-gray-900">Already Paid</h1>
            <p class="text-gray-500 mt-2">This payment has already been completed. You cannot pay twice for the same transaction.</p>
            <div class="mt-4 bg-blue-50 rounded-xl p-3">
                <p class="text-xs text-blue-700 font-mono">{{ $transaction->public_id }}</p>
                <p class="text-xs text-blue-500 mt-1">Status: {{ $transaction->status }}</p>
            </div>
            <p class="text-xs text-gray-400 mt-6">PayMock Gateway &mdash; Test Environment</p>
        </div>
    </div>
</body>
</html>
