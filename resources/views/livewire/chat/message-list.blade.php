<div
    class="flex h-full flex-col gap-4 overflow-y-auto p-4 sm:p-6"
    data-chat-message-list
    data-conversation-id="{{ $conversationId }}"
>
    @forelse ($messages as $message)
        @php
            $isUser = $message->role === \App\Enums\MessageRole::User;
        @endphp

        <div
            wire:key="message-{{ $message->id }}"
            class="flex {{ $isUser ? 'justify-end' : 'justify-start' }}"
            @if (! $isUser)
                data-assistant-message-id="{{ $message->id }}"
            @endif
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

                <p
                    class="whitespace-pre-wrap break-words text-sm leading-6"
                    @if (! $isUser)
                        data-message-content
                    @endif
                >
                    @if (
                        ! $isUser
                        && $message->content === ''
                        && $message->status === \App\Models\Message::STATUS_STREAMING
                    )
                        Escribiendo…
                    @elseif (
                        ! $isUser
                        && $message->content === ''
                        && $message->status === \App\Models\Message::STATUS_FAILED
                    )
                        No fue posible completar esta respuesta.
                    @elseif (
                        ! $isUser
                        && $message->content === ''
                        && $message->status === \App\Models\Message::STATUS_INTERRUPTED
                    )
                        La respuesta fue interrumpida.
                    @else
                        {{ $message->content }}
                    @endif
                </p>

                @if (! $isUser)
                    @if ($message->status === \App\Models\Message::STATUS_STREAMING)
                        <p
                            class="mt-2 text-xs text-zinc-500"
                            data-message-status
                        >
                            Escribiendo…
                        </p>
                    @elseif ($message->status === \App\Models\Message::STATUS_FAILED)
                        <div class="mt-3 flex items-center gap-3">
                            <span
                                class="text-xs text-red-400"
                                data-message-status
                            >
                                Respuesta fallida
                            </span>

                            <button
                                type="button"
                                data-chat-retry="{{ $message->id }}"
                                class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-300 transition hover:bg-zinc-800"
                            >
                                Reintentar
                            </button>
                        </div>
                    @elseif ($message->status === \App\Models\Message::STATUS_INTERRUPTED)
                        <div class="mt-3 flex items-center gap-3">
                            <span
                                class="text-xs text-amber-400"
                                data-message-status
                            >
                                Respuesta interrumpida
                            </span>

                            <button
                                type="button"
                                data-chat-retry="{{ $message->id }}"
                                class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-300 transition hover:bg-zinc-800"
                            >
                                Reintentar
                            </button>
                        </div>
                    @endif
                @endif

                <p class="mt-2 text-right text-[11px] text-zinc-600">
                    {{ $message->created_at->format('H:i') }}
                </p>
            </article>
        </div>
    @empty
        <div
            class="flex flex-1 items-center justify-center p-6 text-center"
            data-chat-empty-state
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
                    Escribe el primer mensaje de esta conversación.
                </p>
            </div>
        </div>
    @endforelse
</div>
