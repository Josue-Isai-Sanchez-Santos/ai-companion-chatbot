<section class="mx-auto w-full max-w-6xl">
    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">
                Personaje activo
            </p>

            <h1
                class="mt-2 truncate text-2xl font-semibold text-zinc-100 sm:text-3xl"
                data-testid="character-name"
            >
                {{ $profile->nickname_for_character ?: $character->name }}
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-400 sm:text-base">
                {{ $character->description }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2 sm:justify-end">
            <button
                type="button"
                disabled
                title="Disponible próximamente"
                class="cursor-not-allowed rounded-lg border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-400 opacity-70"
            >
                Configuración
            </button>

            <button
                type="button"
                disabled
                title="Disponible próximamente"
                class="cursor-not-allowed rounded-lg border border-red-900/70 px-4 py-2 text-sm font-medium text-red-400 opacity-70"
            >
                Restablecer personaje
            </button>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-4 sm:p-5">
            <div
                class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950"
            >
                @if ($character->avatar_path)
                    <img
                        src="{{ asset('storage/' . $character->avatar_path) }}"
                        alt="Avatar de {{ $character->name }}"
                        class="h-full w-full object-cover"
                    >
                @else
                    <span
                        class="select-none text-6xl font-semibold text-zinc-700"
                        aria-hidden="true"
                    >
                        {{ mb_strtoupper(mb_substr($character->name, 0, 1)) }}
                    </span>
                @endif
            </div>

            <div class="mt-5 space-y-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">
                        Expresión actual
                    </p>

                    <p
                        class="mt-1 font-medium text-zinc-200"
                        data-testid="current-expression"
                    >
                        {{ $expressionLabel }}
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">
                        Estado de ánimo
                    </p>

                    <p
                        class="mt-1 font-medium text-zinc-200"
                        data-testid="current-mood"
                    >
                        {{ $moodLabel }}
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">
                        Relación
                    </p>

                    <p
                        class="mt-1 font-medium text-zinc-200"
                        data-testid="relationship-stage"
                    >
                        {{ $relationshipLabel }}
                    </p>
                </div>
            </div>
        </aside>

        <section
            class="flex min-h-[32rem] flex-col overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/70 lg:min-h-[38rem]"
        >
            <header class="border-b border-zinc-800 px-4 py-4 sm:px-5">
                <h2 class="font-semibold text-zinc-100">
                    Conversación
                </h2>

                <p class="mt-1 text-sm text-zinc-500">
                    El sistema de mensajes se implementará en el siguiente punto.
                </p>
            </header>

            <div
                class="flex flex-1 items-center justify-center p-6 text-center sm:p-10"
                data-testid="message-area"
            >
                <div class="max-w-sm">
                    <div
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-zinc-800 bg-zinc-950 text-xl text-zinc-600"
                        aria-hidden="true"
                    >
                        …
                    </div>

                    <h3 class="mt-4 font-medium text-zinc-300">
                        Todavía no hay mensajes
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-zinc-500">
                        Aquí aparecerá el historial de conversación cuando
                        implementemos el sistema de mensajes.
                    </p>
                </div>
            </div>

            <div class="border-t border-zinc-800 bg-zinc-950/40 p-3 sm:p-4">
                <div class="flex items-end gap-2">
                    <label for="message" class="sr-only">
                        Escribe un mensaje
                    </label>

                    <textarea
                        id="message"
                        rows="1"
                        disabled
                        placeholder="La escritura estará disponible próximamente..."
                        class="min-h-11 flex-1 resize-none rounded-xl border border-zinc-800 bg-zinc-950 px-4 py-3 text-sm text-zinc-500 outline-none disabled:cursor-not-allowed disabled:opacity-70"
                    ></textarea>

                    <button
                        type="button"
                        disabled
                        class="h-11 shrink-0 cursor-not-allowed rounded-xl bg-zinc-700 px-4 text-sm font-semibold text-zinc-400 opacity-70"
                    >
                        Enviar
                    </button>
                </div>
            </div>
        </section>
    </div>
</section>
