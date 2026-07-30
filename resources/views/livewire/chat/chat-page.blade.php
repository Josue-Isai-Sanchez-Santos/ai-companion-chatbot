<section class="space-y-6">
    <div>
        <p class="text-sm font-medium uppercase tracking-wider text-zinc-500">
            Versión 1.0 en desarrollo
        </p>

        <h1 class="mt-2 text-3xl font-semibold">
            Chat
        </h1>

        <p class="mt-2 max-w-2xl text-zinc-400">
            La autenticación está activa. El personaje, las conversaciones y
            los mensajes se implementarán en los siguientes puntos.
        </p>
    </div>

    <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-6">
        <h2 class="font-medium">
            Sesión autenticada
        </h2>

        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-zinc-500">
                    Usuario
                </dt>

                <dd class="mt-1">
                    {{ auth()->user()->name }}
                </dd>
            </div>

            <div>
                <dt class="text-sm text-zinc-500">
                    Correo
                </dt>

                <dd class="mt-1">
                    {{ auth()->user()->email }}
                </dd>
            </div>
        </dl>
    </div>
</section>
