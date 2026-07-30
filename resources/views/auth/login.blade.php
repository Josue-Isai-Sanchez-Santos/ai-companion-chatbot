@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <h1 class="text-2xl font-semibold">
        Iniciar sesión
    </h1>

    <p class="mt-2 text-sm text-zinc-400">
        Ingresa a tu cuenta para continuar la conversación.
    </p>

    @if ($errors->any())
        <div
            class="mt-5 rounded-lg border border-red-900 bg-red-950/50 p-4 text-sm text-red-200"
            role="alert"
        >
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('login') }}"
        class="mt-6 space-y-5"
    >
        @csrf

        <div>
            <label
                for="email"
                class="mb-2 block text-sm font-medium"
            >
                Correo electrónico
            </label>

            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none focus:border-zinc-400"
            >
        </div>

        <div>
            <label
                for="password"
                class="mb-2 block text-sm font-medium"
            >
                Contraseña
            </label>

            <input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none focus:border-zinc-400"
            >
        </div>

        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input
                name="remember"
                type="checkbox"
                value="1"
                class="rounded border-zinc-700 bg-zinc-950"
            >

            Recordar sesión
        </label>

        <button
            type="submit"
            class="w-full rounded-lg bg-white px-4 py-2.5 font-medium text-zinc-950 transition hover:bg-zinc-200"
        >
            Iniciar sesión
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-zinc-400">
        ¿Todavía no tienes una cuenta?

        <a
            href="{{ route('register') }}"
            class="font-medium text-white underline underline-offset-4"
        >
            Registrarse
        </a>
    </p>
@endsection
