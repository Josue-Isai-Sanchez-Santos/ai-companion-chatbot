<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? 'AI Companion Chatbot' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-zinc-950 text-zinc-100">
    <header class="border-b border-zinc-800 bg-zinc-900">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
            <a
                href="{{ route('chat') }}"
                class="text-lg font-semibold"
            >
                AI Companion Chatbot
            </a>

            <div class="flex items-center gap-4">
                <span class="hidden text-sm text-zinc-400 sm:inline">
                    {{ auth()->user()->name }}
                </span>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="rounded-lg border border-zinc-700 px-3 py-2 text-sm transition hover:bg-zinc-800"
                    >
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
