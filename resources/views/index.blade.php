
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to PastorEyes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-800">
    <nav class="bg-white border-b border-gray-200 px-4 lg:px-6 py-3">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="{{ env('APP_URL') }}/icons/pastoreyes-logo.png" alt="PastorEyes Logo" class="w-8 h-8">
                <span class="text-xl font-bold text-gray-800 tracking-tight">PastorEyes</span>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors font-medium">
                    Sign in
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 lg:px-6 py-12">
        <div class="flex flex-col items-center justify-center min-h-[60vh]">
            <img src="{{ env('APP_URL') }}/icons/pastoreyes-logo.png" alt="PastorEyes Logo" class="w-24 h-24 mb-6">
            <h1 class="text-3xl font-bold mb-2">Welcome to PastorEyes</h1>
            <p class="text-lg text-gray-600 mb-4 text-center max-w-xl">
                PastorEyes is an online platform for managing pastoral contacts, helping you keep track of your community and care for your people more effectively.
            </p>
            <a href="{{ route('login') }}" class="mt-2 px-6 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors font-medium">
                Sign in to get started
            </a>
        </div>
    </main>

    <footer>
        <div class="max-w-7xl mx-auto px-4 lg:px-6 py-4 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} PastorEyes. All rights reserved. &middot;
            <a href="{{ route('privacy') }}" class="hover:underline">Privacy Policy</a> &middot;
            <a href="{{ route('terms') }}" class="hover:underline">Terms of Service</a>
        </div>
    </footer>
</body>
</html>
