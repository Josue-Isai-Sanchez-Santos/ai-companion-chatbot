@extends('layouts.guest')

@section('title', 'Crear cuenta')

@section('content')
    <h1 class="text-2xl font-semibold">
        Crear cuenta
    </h1>

    <p class="mt-2 text-sm text-zinc-400">
        Crea una cuenta para guardar conversaciones y progreso.
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
        action="{{ route('register') }}"
        class="mt-6 space-y-5"
    >
        @csrf

        <div>
            <label
                for="name"
                class="mb-2 block text-sm font-medium"
            >
                Nombre
            </label>

            <input
                id="name"
                name="name"
                type="text"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none focus:border-zinc-400"
            >
        </div>

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
                autocomplete="new-password"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none focus:border-zinc-400"
            >
        </div>

        <div>
            <label
                for="password_confirmation"
                class="mb-2 block text-sm font-medium"
            >
                Confirmar contraseña
            </label>

            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-zinc-100 outline-none focus:border-zinc-400"
            >
        </div>

        <button
            type="submit"
            class="w-full rounded-lg bg-white px-4 py-2.5 font-medium text-zinc-950 transition hover:bg-zinc-200"
        >
            Crear cuenta
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-zinc-400">
        ¿Ya tienes una cuenta?

        <a
            href="{{ route('login') }}"
            class="font-medium text-white underline underline-offset-4"
        >
            Iniciar sesión
        </a>
    </p>
@endsection
