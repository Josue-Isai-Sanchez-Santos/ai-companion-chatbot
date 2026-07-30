<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'AI Companion Chatbot')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-zinc-950 text-zinc-100">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <a
                    href="{{ route('home') }}"
                    class="text-2xl font-semibold tracking-tight"
                >
                    AI Companion Chatbot
                </a>

                <p class="mt-2 text-sm text-zinc-400">
                    Conversación persistente con personalidad y memoria.
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl">
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
