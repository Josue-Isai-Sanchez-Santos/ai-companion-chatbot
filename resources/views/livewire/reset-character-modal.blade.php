<div>
    <button
        type="button"
        wire:click="openModal"
        class="rounded-lg border border-red-900/70 px-4 py-2 text-sm font-medium text-red-400 transition hover:bg-red-950/30"
    >
        Restablecer personaje
    </button>

    @if ($open)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reset-character-title"
        >
            <div
                class="w-full max-w-lg rounded-2xl border border-red-900/70 bg-zinc-950 p-6 shadow-2xl"
            >
                <h2
                    id="reset-character-title"
                    class="text-xl font-semibold text-zinc-100"
                >
                    Restablecer personaje
                </h2>

                <p class="mt-3 text-sm leading-6 text-zinc-400">
                    Esta operación elimina toda la historia compartida y devuelve el personaje a su estado inicial para tu cuenta.
                </p>

                <div class="mt-4 rounded-xl border border-red-950 bg-red-950/20 p-4">
                    <p class="text-sm font-medium text-red-300">
                        Se eliminarán permanentemente:
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-zinc-400">
                        <li>Conversaciones, mensajes y ramas.</li>
                        <li>Resúmenes, memorias y embeddings.</li>
                        <li>Personalización y escenario personalizado.</li>
                        <li>Progreso y eventos de relación.</li>
                        <li>Estado emocional y expresión actual.</li>
                        <li>Archivos generados para este perfil.</li>
                    </ul>
                </div>

                <p class="mt-4 text-sm leading-6 text-zinc-400">
                    Tu cuenta, el personaje base, sus expresiones base, avatar y configuración global se conservarán.
                </p>

                <form
                    method="POST"
                    action="{{ route('character.reset') }}"
                    class="mt-5 space-y-4"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="profile_id"
                        value="{{ $profileId }}"
                    >

                    <div>
                        <label
                            for="reset-confirmation"
                            class="block text-sm font-medium text-zinc-300"
                        >
                            Escribe
                            <span class="font-semibold text-red-400">
                                {{ $confirmationWord }}
                            </span>
                            para confirmar
                        </label>

                        <input
                            id="reset-confirmation"
                            type="text"
                            name="confirmation"
                            value="{{ old('confirmation') }}"
                            autocomplete="off"
                            spellcheck="false"
                            class="mt-2 w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-3 text-zinc-100 outline-none focus:border-red-700"
                        >

                        @error('confirmation')
                            <p class="mt-2 text-sm text-red-400">
                                {{ $message }}
                            </p>
                        @enderror

                        @error('profile_id')
                            <p class="mt-2 text-sm text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-300 transition hover:bg-zinc-800"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-600"
                        >
                            Borrar y restablecer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
