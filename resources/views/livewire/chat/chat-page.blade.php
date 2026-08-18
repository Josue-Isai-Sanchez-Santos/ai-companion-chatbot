<section class="mx-auto w-full max-w-7xl">
    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">
                Personaje activo
            </p>

            <h1 class="mt-2 truncate text-2xl font-semibold text-zinc-100 sm:text-3xl">
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
                class="cursor-not-allowed rounded-lg border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-400 opacity-70"
            >
                Configuración
            </button>

            <button
                type="button"
                disabled
                class="cursor-not-allowed rounded-lg border border-red-900/70 px-4 py-2 text-sm font-medium text-red-400 opacity-70"
            >
                Restablecer personaje
            </button>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-4">
            <div class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950">
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

                    <p class="mt-1 font-medium text-zinc-200">
                        {{ $expressionLabel }}
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">
                        Estado de ánimo
                    </p>

                    <p class="mt-1 font-medium text-zinc-200">
                        {{ $moodLabel }}
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">
                        Relación
                    </p>

                    <p class="mt-1 font-medium text-zinc-200">
                        {{ $relationshipLabel }}
                    </p>
                </div>
            </div>
        </aside>

        <div class="grid min-w-0 gap-4 md:grid-cols-[17rem_minmax(0,1fr)]">
            <aside class="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/70">
                <header class="flex items-center justify-between gap-3 border-b border-zinc-800 p-4">
                    <h2 class="font-semibold text-zinc-100">
                        Conversaciones
                    </h2>

                    <button
                        type="button"
                        wire:click="createConversation"
                        class="shrink-0 rounded-lg border border-zinc-700 px-3 py-2 text-xs font-medium text-zinc-200 transition hover:bg-zinc-800"
                    >
                        Nueva
                    </button>
                </header>

                <div class="max-h-[36rem] space-y-2 overflow-y-auto p-3">
                    @forelse ($conversations as $conversation)
                        <div
                            wire:key="conversation-{{ $conversation->id }}"
                            class="rounded-xl border p-2
                                {{ $conversationId === $conversation->id
                                    ? 'border-zinc-600 bg-zinc-800/80'
                                    : 'border-zinc-800 bg-zinc-950/50' }}"
                        >
                            @if ($renamingConversationId === $conversation->id)
                                <form
                                    wire:submit="renameConversation"
                                    class="space-y-2"
                                >
                                    <input
                                        type="text"
                                        wire:model="renamingTitle"
                                        maxlength="160"
                                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-zinc-500"
                                        autofocus
                                    >

                                    @error('renamingTitle')
                                        <p class="text-xs text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror

                                    <div class="flex gap-2">
                                        <button
                                            type="submit"
                                            class="rounded-md bg-zinc-700 px-2 py-1 text-xs text-zinc-100 hover:bg-zinc-600"
                                        >
                                            Guardar
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="cancelRenamingConversation"
                                            class="rounded-md px-2 py-1 text-xs text-zinc-400 hover:bg-zinc-800"
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            @else
                                <button
                                    type="button"
                                    wire:click="selectConversation({{ $conversation->id }})"
                                    class="block w-full min-w-0 text-left"
                                >
                                    <span class="block truncate text-sm font-medium text-zinc-200">
                                        {{ $conversation->title }}
                                    </span>

                                    <span class="mt-1 block text-xs text-zinc-500">
                                        {{ $conversation->updated_at->format('d/m/Y H:i') }}
                                    </span>
                                </button>

                                <div class="mt-2 flex gap-2 border-t border-zinc-800 pt-2">
                                    <button
                                        type="button"
                                        wire:click="startRenamingConversation({{ $conversation->id }})"
                                        class="text-xs text-zinc-400 hover:text-zinc-200"
                                    >
                                        Renombrar
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="deleteConversation({{ $conversation->id }})"
                                        wire:confirm="¿Eliminar esta conversación?"
                                        class="text-xs text-red-500 hover:text-red-400"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-3 py-8 text-center">
                            <p class="text-sm text-zinc-400">
                                No hay conversaciones.
                            </p>

                            <p class="mt-1 text-xs leading-5 text-zinc-600">
                                Crea una para comenzar.
                            </p>
                        </div>
                    @endforelse
                </div>
            </aside>

            <section class="flex min-h-[32rem] min-w-0 flex-col overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/70 lg:min-h-[38rem]">
                <header class="border-b border-zinc-800 px-4 py-4 sm:px-5">
                    @if ($selectedConversation)
                        <h2 class="truncate font-semibold text-zinc-100">
                            {{ $selectedConversation->title }}
                        </h2>

                        <p class="mt-1 text-sm text-zinc-500">
                            Conversación seleccionada
                        </p>
                    @else
                        <h2 class="font-semibold text-zinc-100">
                            Conversación
                        </h2>

                        <p class="mt-1 text-sm text-zinc-500">
                            Selecciona o crea una conversación.
                        </p>
                    @endif
                </header>

                @if ($selectedConversation)
                    <div class="min-h-0 flex-1">
                        <livewire:chat.message-list
                            :conversation-id="$selectedConversation->id"
                            :key="'messages-'.$selectedConversation->id"
                        />
                    </div>
                @else
                    <div class="flex flex-1 items-center justify-center p-6 text-center sm:p-10">
                        <div class="max-w-sm">
                            <div
                                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-zinc-800 bg-zinc-950 text-xl text-zinc-600"
                                aria-hidden="true"
                            >
                                …
                            </div>

                            <h3 class="mt-4 font-medium text-zinc-300">
                                Ninguna conversación seleccionada
                            </h3>

                            <p class="mt-2 text-sm leading-6 text-zinc-500">
                                Usa el botón “Nueva” para crear tu primera conversación.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="border-t border-zinc-800 bg-zinc-950/40 p-3 sm:p-4">
                    <form
                        data-chat-stream-form
                        data-conversation-id="{{ $selectedConversation?->id }}"
                        data-stream-url="{{ route('chat.stream') }}"
                        data-retry-url="{{ route('chat.stream.retry') }}"
                        class="space-y-2"
                    >
                        @csrf

                        <div class="flex items-end gap-2">
                            <label for="message" class="sr-only">
                                Escribe un mensaje
                            </label>

                            <textarea
                                id="message"
                                name="message"
                                rows="1"
                                data-chat-message
                                maxlength="{{ config('chatbot.message_max_length') }}"
                                @disabled($selectedConversation === null)
                                placeholder="{{ $selectedConversation
                                    ? 'Escribe un mensaje...'
                                    : 'Selecciona una conversación...' }}"
                                class="min-h-11 flex-1 resize-none rounded-xl border border-zinc-800 bg-zinc-950 px-4 py-3 text-sm text-zinc-100 outline-none placeholder:text-zinc-600 focus:border-zinc-600 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>

                            <button
                                type="submit"
                                data-chat-submit
                                @disabled($selectedConversation === null)
                                class="h-11 shrink-0 rounded-xl bg-zinc-100 px-4 text-sm font-semibold text-zinc-950 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                Enviar
                            </button>
                        </div>

                        <p
                            data-chat-status
                            class="hidden text-sm text-zinc-500"
                        ></p>

                        <p
                            data-chat-error
                            class="hidden text-sm text-red-400"
                        ></p>

                        @error('message')
                            <p class="text-sm text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </form>
                </div>
            </section>
        </div>
    </div>
</section>
