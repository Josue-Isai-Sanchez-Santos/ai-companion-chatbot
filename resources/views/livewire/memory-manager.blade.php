<section class="mx-auto w-full max-w-6xl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">
                Memoria del personaje
            </p>

            <h1 class="mt-2 text-2xl font-semibold text-zinc-100 sm:text-3xl">
                Gestor de memorias
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-400">
                Revisa y controla lo que
                {{ $profile->nickname_for_character ?: $character->name }}
                recuerda de tus conversaciones.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('chat') }}"
                class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-300 transition hover:bg-zinc-800"
            >
                Volver al chat
            </a>

            <button
                type="button"
                wire:click="startCreating"
                class="rounded-lg bg-zinc-100 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-white"
            >
                Nueva memoria
            </button>
        </div>
    </div>

    @if ($statusMessage)
        <div class="mb-5 rounded-xl border border-emerald-900/60 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-300">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="mb-5 rounded-2xl border border-zinc-800 bg-zinc-900/70 p-4">
        <label
            for="memory-type-filter"
            class="block text-xs font-medium uppercase tracking-wider text-zinc-500"
        >
            Filtrar por tipo
        </label>

        <select
            id="memory-type-filter"
            wire:model.live="typeFilter"
            class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200 outline-none focus:border-zinc-500 sm:max-w-sm"
        >
            <option value="all">
                Todas las memorias
            </option>

            @foreach ($typeLabels as $value => $label)
                <option value="{{ $value }}">
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    @if ($showForm)
        <form
            wire:submit="saveMemory"
            class="mb-6 rounded-2xl border border-zinc-700 bg-zinc-900 p-5"
        >
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-zinc-100">
                        {{ $editingMemoryId === null
                            ? 'Crear memoria manual'
                            : 'Editar memoria' }}
                    </h2>

                    <p class="mt-1 text-sm text-zinc-500">
                        El texto se convertirá en un embedding para poder recuperarlo en conversaciones futuras.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="cancelForm"
                    class="text-sm text-zinc-400 hover:text-zinc-200"
                >
                    Cancelar
                </button>
            </div>

            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div>
                    <label
                        for="memory-type"
                        class="block text-sm font-medium text-zinc-300"
                    >
                        Tipo
                    </label>

                    <select
                        id="memory-type"
                        wire:model.live="formType"
                        class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-zinc-500"
                    >
                        @foreach ($typeLabels as $value => $label)
                            <option value="{{ $value }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>

                    @error('formType')
                        <p class="mt-1 text-xs text-red-400">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                @if (
                    $formType
                    === \App\Enums\MemoryType::TemporaryContext->value
                )
                    <div>
                        <label
                            for="memory-expiration"
                            class="block text-sm font-medium text-zinc-300"
                        >
                            Expira
                        </label>

                        <input
                            id="memory-expiration"
                            type="datetime-local"
                            wire:model="formExpiresAt"
                            class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-zinc-500"
                        >

                        @error('formExpiresAt')
                            <p class="mt-1 text-xs text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                @endif
            </div>

            <div class="mt-5">
                <label
                    for="memory-content"
                    class="block text-sm font-medium text-zinc-300"
                >
                    Contenido
                </label>

                <textarea
                    id="memory-content"
                    wire:model="formContent"
                    rows="4"
                    maxlength="1000"
                    class="mt-2 w-full resize-y rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-3 text-sm leading-6 text-zinc-100 outline-none focus:border-zinc-500"
                    placeholder="Ejemplo: El usuario prefiere café sin azúcar."
                ></textarea>

                @error('formContent')
                    <p class="mt-1 text-xs text-red-400">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label
                        for="memory-importance"
                        class="block text-sm font-medium text-zinc-300"
                    >
                        Importancia
                    </label>

                    <input
                        id="memory-importance"
                        type="number"
                        min="0"
                        max="1"
                        step="0.05"
                        wire:model="formImportance"
                        class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-zinc-500"
                    >

                    <p class="mt-1 text-xs text-zinc-600">
                        0 = poco importante, 1 = muy importante.
                    </p>

                    @error('formImportance')
                        <p class="mt-1 text-xs text-red-400">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="memory-confidence"
                        class="block text-sm font-medium text-zinc-300"
                    >
                        Confianza
                    </label>

                    <input
                        id="memory-confidence"
                        type="number"
                        min="0"
                        max="1"
                        step="0.05"
                        wire:model="formConfidence"
                        class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-zinc-500"
                    >

                    <p class="mt-1 text-xs text-zinc-600">
                        0 = incierto, 1 = completamente confirmado.
                    </p>

                    @error('formConfidence')
                        <p class="mt-1 text-xs text-red-400">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="cancelForm"
                    class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="saveMemory"
                    class="rounded-lg bg-zinc-100 px-4 py-2 text-sm font-semibold text-zinc-950 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="saveMemory">
                        {{ $editingMemoryId === null
                            ? 'Crear memoria'
                            : 'Guardar cambios' }}
                    </span>

                    <span wire:loading wire:target="saveMemory">
                        Guardando…
                    </span>
                </button>
            </div>
        </form>
    @endif

    <div class="space-y-3">
        @forelse ($memories as $memory)
            <article
                wire:key="memory-{{ $memory->id }}"
                class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-zinc-700 bg-zinc-950 px-2.5 py-1 text-xs font-medium text-zinc-300">
                                {{ $typeLabels[$memory->type->value] }}
                            </span>

                            @if (
                                $memory->expires_at
                                && $memory->expires_at->isPast()
                            )
                                <span class="rounded-full border border-amber-900/70 bg-amber-950/30 px-2.5 py-1 text-xs text-amber-400">
                                    Expirada
                                </span>
                            @elseif ($memory->expires_at)
                                <span class="rounded-full border border-zinc-700 px-2.5 py-1 text-xs text-zinc-400">
                                    Expira {{ $memory->expires_at->format('d/m/Y H:i') }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-zinc-100">
                            {{ $memory->content }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs text-zinc-500">
                            <span>
                                Importancia:
                                <strong class="font-medium text-zinc-300">
                                    {{ number_format($memory->importance * 100) }}%
                                </strong>
                            </span>

                            <span>
                                Confianza:
                                <strong class="font-medium text-zinc-300">
                                    {{ number_format($memory->confidence * 100) }}%
                                </strong>
                            </span>

                            <span>
                                Usada:
                                <strong class="font-medium text-zinc-300">
                                    {{ $memory->access_count }}
                                </strong>
                                veces
                            </span>
                        </div>

                        @if ($memory->sourceMessage)
                            <div class="mt-4 rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
                                <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">
                                    Origen
                                </p>

                                <p class="mt-2 text-xs text-zinc-500">
                                    @if ($memory->sourceMessage->conversation)
                                        {{ $memory->sourceMessage->conversation->title }}
                                        ·
                                    @endif

                                    Mensaje #{{ $memory->sourceMessage->id }}
                                    ·
                                    {{ $memory->sourceMessage->created_at->format('d/m/Y H:i') }}
                                </p>

                                <p class="mt-2 text-sm leading-6 text-zinc-400">
                                    {{ \Illuminate\Support\Str::limit(
                                        $memory->sourceMessage->content,
                                        240
                                    ) }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <button
                            type="button"
                            wire:click="startEditing({{ $memory->id }})"
                            class="rounded-lg border border-zinc-700 px-3 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-800"
                        >
                            Editar
                        </button>

                        <button
                            type="button"
                            wire:click="deleteMemory({{ $memory->id }})"
                            wire:confirm="¿Eliminar esta memoria?"
                            class="rounded-lg border border-red-900/70 px-3 py-2 text-xs font-medium text-red-400 transition hover:bg-red-950/30"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-800 bg-zinc-900/40 px-6 py-14 text-center">
                <h2 class="font-medium text-zinc-300">
                    No hay memorias para mostrar
                </h2>

                <p class="mt-2 text-sm text-zinc-500">
                    Puedes crear una manualmente o conversar con el personaje para generar recuerdos automáticamente.
                </p>
            </div>
        @endforelse
    </div>
</section>
