
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
        <div class="flex flex-col items-center justify-center text-center py-12">
            <img src="{{ env('APP_URL') }}/icons/pastoreyes-logo.png" alt="PastorEyes Logo" class="w-20 h-20 mb-6">
            <h1 class="text-3xl md:text-4xl font-bold mb-4 max-w-2xl">
                Remember everyone you care for, and everything that matters to them.
            </h1>
            <p class="text-lg text-gray-600 mb-8 max-w-2xl">
                When you're caring pastorally for dozens or hundreds of people, it's impossible to
                hold it all in your head. PastorEyes gives you a private, secure place to keep track
                of who's who, how they're connected, and what's going on for them — so you can walk
                back into a conversation months later and pick up exactly where you left off.
            </p>
            <a href="{{ route('login') }}" class="px-6 py-3 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors font-medium">
                Sign in with Google to get started
            </a>
        </div>

        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 py-8">
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h2 class="font-semibold text-gray-900 mb-2">Names, addresses &amp; key dates</h2>
                <p class="text-sm text-gray-600">
                    Track former names, cope with unknown surnames or spellings, keep addresses
                    dated so you know how current they are, and never miss a birthday or anniversary.
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h2 class="font-semibold text-gray-900 mb-2">Visual relationship maps</h2>
                <p class="text-sm text-gray-600">
                    See how people connect — family, friendships, custom relationships — in an
                    interactive family-tree style graph you can click through to explore.
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h2 class="font-semibold text-gray-900 mb-2">Notes, goals &amp; a timeline</h2>
                <p class="text-sm text-gray-600">
                    Log dated notes on meetings and events, track mentoring goals and outcomes, and
                    review everything in a single timeline you can filter by significance.
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h2 class="font-semibold text-gray-900 mb-2">Google Contacts &amp; Calendar</h2>
                <p class="text-sm text-gray-600">
                    Link people to your Google contacts and sync key dates so they show up
                    where you already look.
                </p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h2 class="font-semibold text-gray-900 mb-2">Private by design</h2>
                <p class="text-sm text-gray-600">
                    Your data is yours alone. Every user has a fully separate dataset, and all
                    sensitive text is encrypted at rest with a key unique to your account.
                </p>
            </div>
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
