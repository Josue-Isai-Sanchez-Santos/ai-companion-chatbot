let activeStream = false;

function currentForm() {
    return document.querySelector(
        '[data-chat-stream-form]'
    );
}

function currentMessageList(conversationId) {
    return document.querySelector(
        `[data-chat-message-list][data-conversation-id="${conversationId}"]`
    );
}

function setBusy(form, busy) {
    const textarea = form.querySelector(
        '[data-chat-message]'
    );

    const button = form.querySelector(
        '[data-chat-submit]'
    );

    const hasConversation = Boolean(
        form.dataset.conversationId
    );

    if (textarea) {
        textarea.disabled = busy
            || ! hasConversation;
    }

    if (button) {
        button.disabled = busy
            || ! hasConversation;
    }
}

function showStatus(form, message = '') {
    const element = form.querySelector(
        '[data-chat-status]'
    );

    if (! element) {
        return;
    }

    element.textContent = message;

    element.classList.toggle(
        'hidden',
        message === ''
    );
}

function showError(form, message = '') {
    const element = form.querySelector(
        '[data-chat-error]'
    );

    if (! element) {
        return;
    }

    element.textContent = message;

    element.classList.toggle(
        'hidden',
        message === ''
    );
}

function csrfToken(form) {
    return form.querySelector(
        'input[name="_token"]'
    )?.value ?? '';
}

function removeEmptyState(container) {
    container
        ?.querySelector('[data-chat-empty-state]')
        ?.remove();
}

function scrollMessages(container) {
    if (! container) {
        return;
    }

    container.scrollTop =
        container.scrollHeight;
}

function appendOptimisticUser(
    conversationId,
    content
) {
    const container = currentMessageList(
        conversationId
    );

    if (! container) {
        return null;
    }

    removeEmptyState(container);

    const wrapper = document.createElement(
        'div'
    );

    wrapper.className =
        'flex justify-end';

    wrapper.dataset.optimisticUser = 'true';

    const article = document.createElement(
        'article'
    );

    article.className =
        'max-w-[85%] rounded-2xl bg-zinc-700 px-4 py-3 text-zinc-100 sm:max-w-[75%]';

    const label = document.createElement(
        'p'
    );

    label.className =
        'mb-1 text-xs font-medium text-zinc-500';

    label.textContent = 'Tú';

    const text = document.createElement(
        'p'
    );

    text.className =
        'whitespace-pre-wrap break-words text-sm leading-6';

    text.textContent = content;

    article.append(
        label,
        text
    );

    wrapper.appendChild(article);

    container.appendChild(wrapper);

    scrollMessages(container);

    return wrapper;
}

function ensureAssistantBubble(
    conversationId,
    assistantId
) {
    let wrapper = document.querySelector(
        `[data-assistant-message-id="${assistantId}"]`
    );

    if (wrapper) {
        return wrapper;
    }

    const container = currentMessageList(
        conversationId
    );

    if (! container) {
        return null;
    }

    removeEmptyState(container);

    wrapper = document.createElement(
        'div'
    );

    wrapper.className =
        'flex justify-start';

    wrapper.dataset.assistantMessageId =
        String(assistantId);

    const article = document.createElement(
        'article'
    );

    article.className =
        'max-w-[85%] rounded-2xl border border-zinc-800 bg-zinc-950 px-4 py-3 text-zinc-200 sm:max-w-[75%]';

    const label = document.createElement(
        'p'
    );

    label.className =
        'mb-1 text-xs font-medium text-zinc-500';

    label.textContent = 'Personaje';

    const text = document.createElement(
        'p'
    );

    text.className =
        'whitespace-pre-wrap break-words text-sm leading-6';

    text.dataset.messageContent = '';

    const status = document.createElement(
        'p'
    );

    status.className =
        'mt-2 text-xs text-zinc-500';

    status.dataset.messageStatus = '';

    status.textContent = 'Escribiendo…';

    article.append(
        label,
        text,
        status
    );

    wrapper.appendChild(article);

    container.appendChild(wrapper);

    scrollMessages(container);

    return wrapper;
}

function resetAssistantBubble(
    conversationId,
    assistantId
) {
    const wrapper = ensureAssistantBubble(
        conversationId,
        assistantId
    );

    if (! wrapper) {
        return null;
    }

    const content = wrapper.querySelector(
        '[data-message-content]'
    );

    const status = wrapper.querySelector(
        '[data-message-status]'
    );

    if (content) {
        content.textContent = '';
    }

    if (status) {
        status.textContent =
            'Escribiendo…';

        status.className =
            'mt-2 text-xs text-zinc-500';
    }

    wrapper
        .querySelector('[data-chat-retry]')
        ?.remove();

    return wrapper;
}

function appendDelta(
    conversationId,
    assistantId,
    delta
) {
    const wrapper = ensureAssistantBubble(
        conversationId,
        assistantId
    );

    if (! wrapper) {
        return;
    }

    const content = wrapper.querySelector(
        '[data-message-content]'
    );

    if (content) {
        content.textContent += delta;
    }

    scrollMessages(
        currentMessageList(
            conversationId
        )
    );
}

function setAssistantStatus(
    assistantId,
    message,
    error = false
) {
    const wrapper = document.querySelector(
        `[data-assistant-message-id="${assistantId}"]`
    );

    if (! wrapper) {
        return;
    }

    let status = wrapper.querySelector(
        '[data-message-status]'
    );

    if (! status) {
        status = document.createElement(
            'p'
        );

        status.dataset.messageStatus = '';

        wrapper
            .querySelector('article')
            ?.appendChild(status);
    }

    status.className = error
        ? 'mt-2 text-xs text-red-400'
        : 'mt-2 text-xs text-zinc-500';

    status.textContent = message;
}

function refreshMessages(
    conversationId
) {
    if (! window.Livewire) {
        return;
    }

    window.Livewire.dispatch(
        'messages-updated',
        {
            conversationId:
                Number(conversationId),
        }
    );
}

async function responseError(
    response
) {
    try {
        const data = await response.json();

        if (data.errors) {
            const first = Object
                .values(data.errors)
                .flat()
                .find(Boolean);

            if (first) {
                return first;
            }
        }

        if (data.message) {
            return data.message;
        }
    } catch {
        //
    }

    return `Error HTTP ${response.status}.`;
}

function parseEventBlock(block) {
    let event = 'message';

    const data = [];

    for (
        const line of block.split(/\r?\n/)
    ) {
        if (line.startsWith('event:')) {
            event = line
                .slice(6)
                .trim();

            continue;
        }

        if (line.startsWith('data:')) {
            data.push(
                line.slice(5).trimStart()
            );
        }
    }

    if (data.length === 0) {
        return null;
    }

    let payload;

    try {
        payload = JSON.parse(
            data.join('\n')
        );
    } catch {
        return null;
    }

    return {
        event,
        payload,
    };
}

async function consumeSse(
    response,
    onEvent
) {
    if (! response.body) {
        throw new Error(
            'El navegador no recibió un stream de respuesta.'
        );
    }

    const reader = response.body
        .getReader();

    const decoder = new TextDecoder();

    let buffer = '';
    let terminalEvent = null;

    while (true) {
        const {
            value,
            done,
        } = await reader.read();

        if (done) {
            buffer += decoder.decode();
            break;
        }

        buffer += decoder.decode(
            value,
            {
                stream: true,
            }
        );

        let boundary;

        while (
            (
                boundary =
                    buffer.indexOf('\n\n')
            ) !== -1
        ) {
            const block = buffer
                .slice(0, boundary);

            buffer = buffer
                .slice(boundary + 2);

            const parsed =
                parseEventBlock(block);

            if (! parsed) {
                continue;
            }

            onEvent(
                parsed.event,
                parsed.payload
            );

            if (
                parsed.event
                    === 'completed'
                || parsed.event
                    === 'failed'
            ) {
                terminalEvent =
                    parsed.event;
            }
        }
    }

    if (buffer.trim() !== '') {
        const parsed =
            parseEventBlock(buffer);

        if (parsed) {
            onEvent(
                parsed.event,
                parsed.payload
            );

            if (
                parsed.event
                    === 'completed'
                || parsed.event
                    === 'failed'
            ) {
                terminalEvent =
                    parsed.event;
            }
        }
    }

    return terminalEvent;
}

async function startRequest(
    form,
    url,
    payload,
    conversationId,
    optimisticUser = null
) {
    const response = await fetch(
        url,
        {
            method: 'POST',

            headers: {
                'Content-Type':
                    'application/json',

                'Accept':
                    'application/json, text/event-stream',

                'X-CSRF-TOKEN':
                    csrfToken(form),

                'X-Requested-With':
                    'XMLHttpRequest',
            },

            body: JSON.stringify(
                payload
            ),
        }
    );

    if (! response.ok) {
        throw new Error(
            await responseError(
                response
            )
        );
    }

    const terminalEvent =
        await consumeSse(
            response,

            (
                event,
                data
            ) => {
                switch (event) {
                    case 'started':
                        resetAssistantBubble(
                            conversationId,
                            data.assistant_message_id
                        );

                        showStatus(
                            form,
                            'El personaje está escribiendo…'
                        );

                        break;

                    case 'delta':
                        appendDelta(
                            conversationId,
                            data.assistant_message_id,
                            data.delta
                        );

                        break;

                    case 'completed':
                        setAssistantStatus(
                            data.assistant_message_id,
                            ''
                        );

                        showStatus(
                            form,
                            ''
                        );

                        refreshMessages(
                            conversationId
                        );

                        break;

                    case 'failed':
                        setAssistantStatus(
                            data.assistant_message_id,
                            'Respuesta fallida',
                            true
                        );

                        showStatus(
                            form,
                            ''
                        );

                        showError(
                            form,
                            data.message
                                ?? 'No fue posible completar la respuesta.'
                        );

                        refreshMessages(
                            conversationId
                        );

                        break;
                }
            }
        );

    if (! terminalEvent) {
        optimisticUser?.remove();

        refreshMessages(
            conversationId
        );

        throw new Error(
            'La conexión terminó antes de recibir el estado final de la respuesta.'
        );
    }

    return terminalEvent;
}

document.addEventListener(
    'submit',

    async (event) => {
        const form = event.target
            .closest(
                '[data-chat-stream-form]'
            );

        if (! form) {
            return;
        }

        event.preventDefault();

        if (activeStream) {
            showError(
                form,
                'Espera a que termine la respuesta actual.'
            );

            return;
        }

        const textarea = form.querySelector(
            '[data-chat-message]'
        );

        const conversationId =
            form.dataset.conversationId;

        const message =
            textarea?.value.trim()
            ?? '';

        if (! conversationId) {
            showError(
                form,
                'Selecciona una conversación antes de enviar.'
            );

            return;
        }

        if (message === '') {
            showError(
                form,
                'Escribe un mensaje antes de enviarlo.'
            );

            return;
        }

        activeStream = true;

        showError(
            form,
            ''
        );

        showStatus(
            form,
            'Preparando respuesta…'
        );

        setBusy(
            form,
            true
        );

        const optimisticUser =
            appendOptimisticUser(
                conversationId,
                message
            );

        if (textarea) {
            textarea.value = '';
        }

        try {
            await startRequest(
                form,

                form.dataset.streamUrl,

                {
                    conversation_id:
                        Number(
                            conversationId
                        ),

                    message,
                },

                conversationId,

                optimisticUser
            );
        } catch (error) {
            optimisticUser?.remove();

            showStatus(
                form,
                ''
            );

            showError(
                form,
                error instanceof Error
                    ? error.message
                    : 'No fue posible iniciar la respuesta.'
            );

            refreshMessages(
                conversationId
            );
        } finally {
            activeStream = false;

            setBusy(
                form,
                false
            );

            textarea?.focus();
        }
    }
);

document.addEventListener(
    'click',

    async (event) => {
        const button = event.target
            .closest(
                '[data-chat-retry]'
            );

        if (! button) {
            return;
        }

        const form = currentForm();

        if (! form) {
            return;
        }

        if (activeStream) {
            showError(
                form,
                'Espera a que termine la respuesta actual.'
            );

            return;
        }

        const container = button.closest(
            '[data-chat-message-list]'
        );

        const conversationId =
            container?.dataset
                .conversationId;

        const assistantId =
            button.dataset.chatRetry;

        if (
            ! conversationId
            || ! assistantId
        ) {
            return;
        }

        activeStream = true;

        showError(
            form,
            ''
        );

        showStatus(
            form,
            'Reintentando respuesta…'
        );

        setBusy(
            form,
            true
        );

        resetAssistantBubble(
            conversationId,
            assistantId
        );

        try {
            await startRequest(
                form,

                form.dataset.retryUrl,

                {
                    assistant_message_id:
                        Number(
                            assistantId
                        ),
                },

                conversationId
            );
        } catch (error) {
            showStatus(
                form,
                ''
            );

            showError(
                form,
                error instanceof Error
                    ? error.message
                    : 'No fue posible reintentar la respuesta.'
            );

            refreshMessages(
                conversationId
            );
        } finally {
            activeStream = false;

            setBusy(
                form,
                false
            );
        }
    }
);
