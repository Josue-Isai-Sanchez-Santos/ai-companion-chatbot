<div class="flex h-full flex-col gap-4 overflow-y-auto p-4 sm:p-6">
    @forelse ($messages as $message)
        @php
            $isUser = $message->role === \App\Enums\MessageRole::User;
        @endphp

        <div
            wire:key="message-{{ $message->id }}"
            class="flex {{ $isUser ? 'justify-end' : 'justify-start' }}"
        >
            <article
                class="max-w-[85%] rounded-2xl px-4 py-3 sm:max-w-[75%]
                    {{ $isUser
                        ? 'bg-zinc-700 text-zinc-100'
                        : 'border border-zinc-800 bg-zinc-950 text-zinc-200' }}"
            >
                <p class="mb-1 text-xs font-medium text-zinc-500">
                    {{ $isUser ? 'Tú' : 'Personaje' }}
                </p>

                <p class="whitespace-pre-wrap break-words text-sm leading-6">
                    {{ $message->content }}
                </p>

                <p class="mt-2 text-right text-[11px] text-zinc-600">
                    {{ $message->created_at->format('H:i') }}
                </p>
            </article>
        </div>
    @empty
        <div class="flex flex-1 items-center justify-center p-6 text-center">
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
                    Escribe el primer mensaje de esta conversación.
                </p>
            </div>
        </div>
    @endforelse
</div>
