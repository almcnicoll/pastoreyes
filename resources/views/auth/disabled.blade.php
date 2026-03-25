<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PastorEyes — Account Disabled</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center">

    <div class="w-full max-w-md px-6 py-8 text-center">

        <h1 class="text-4xl font-bold text-gray-800 tracking-tight mb-2">PastorEyes</h1>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 mt-6">

            <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/>
                </svg>
            </div>

            <h2 class="text-lg font-semibold text-gray-800 mb-2">Account Disabled</h2>

            <p class="text-gray-500 text-sm leading-relaxed">
                Your account has been temporarily disabled.
                Please contact your PastorEyes administrator to restore access.
            </p>

            <a href="{{ route('login') }}"
               class="mt-6 inline-block text-sm text-blue-600 hover:underline">
                Back to sign in
            </a>

        </div>

    </div>

</body>
</html>
