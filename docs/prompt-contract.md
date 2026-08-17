# Contrato de construcción de prompts

## Propósito

Definir de forma estable qué información puede enviarse al modelo, en qué orden se construye el contexto y qué reglas debe respetar el personaje al responder.

## Estado

**Implementado para V1.**

La construcción se divide en dos piezas:

1. `systemPrompt`, generado por `CharacterPromptBuilder`.
2. `messages`, preparados por `CharacterAgent` en orden cronológico.

Ambas piezas forman el contexto completo entregado a `ChatGateway`.

## Orden estable del system prompt

`CharacterPromptBuilder` genera siempre las siguientes secciones y en este orden:

1. `01_GLOBAL_RULES`
2. `02_IDENTITY`
3. `03_CHARACTER_RULES`
4. `04_BASE_PERSONALITY`
5. `05_CUSTOM_PERSONALITY`
6. `06_BACKSTORY`
7. `07_BASE_SPEAKING_STYLE`
8. `08_CUSTOM_SPEAKING_STYLE`
9. `09_BASE_SCENARIO`
10. `10_CUSTOM_SCENARIO`
11. `11_CURRENT_STATE`
12. `12_CONVERSATION_SUMMARY`
13. `13_RELEVANT_MEMORIES`
14. `14_RESPONSE_PROTOCOL`

El orden forma parte del contrato y está protegido mediante pruebas.

## Reglas de prioridad

Cuando exista conflicto entre datos o instrucciones, el orden conceptual de prioridad es:

1. Reglas globales de la aplicación.
2. Identidad y reglas base del personaje.
3. Personalidad, historia, estilo y escenario base.
4. Personalización específica del usuario.
5. Estado emocional y de relación.
6. Resumen y memorias relevantes.
7. Mensajes recientes.

La personalización puede complementar la identidad base, pero no reemplazarla ni contradecirla.

## Identidad

El prompt incluye:

- Nombre base.
- Descripción.
- Reglas específicas del personaje.
- Historia base.

La identidad base no cambia como consecuencia de la conversación.

## Personalidad

La personalidad se mantiene en dos capas separadas:

- Personalidad base.
- Personalidad personalizada.

La personalidad personalizada no reemplaza a la base.

## Forma de hablar

Se incluyen por separado:

- Forma de hablar base.
- Forma de hablar personalizada.

Cuando exista un idioma explícitamente configurado, debe respetarse.

Cuando no exista un idioma explícito, el personaje debe continuar en el idioma del mensaje más reciente del usuario.

## Escenario

Se incluyen por separado:

- Escenario base.
- Escenario personalizado.

El escenario personalizado complementa el escenario base.

## Estado actual

El contexto puede incluir:

- Estado emocional.
- Etapa de relación.
- Confianza.
- Afecto.
- Familiaridad.
- Tensión.
- Apodo para el usuario.
- Apodo del personaje.

## Resumen de conversación

Si `conversations.summary` contiene información, se incluye en `12_CONVERSATION_SUMMARY`.

Si todavía no existe resumen, el prompt lo declara explícitamente.

La generación automática de resúmenes pertenece a una fase posterior del roadmap.

## Memorias relevantes

`CharacterPromptBuilder` únicamente utiliza memorias que recibe explícitamente.

No consulta la base de datos.

No crea memorias.

No completa huecos inventando recuerdos.

Toda memoria futura deberá recuperarse previamente usando el perfil del usuario y personaje correspondiente.

Hasta que se implemente el sistema de memoria, esta sección permanece vacía.

## Mensajes recientes

Los mensajes no se convierten en texto dentro del `systemPrompt`.

Se conservan estructurados mediante:

- `role`
- `content`

`CharacterAgent`:

1. obtiene únicamente mensajes de la conversación autorizada;
2. conserva orden cronológico;
3. limita el contexto mediante `CHAT_RECENT_MESSAGE_LIMIT`;
4. coloca el nuevo mensaje del usuario al final.

El límite incluye el mensaje nuevo.

## Aislamiento entre usuarios

`CharacterAgent` autoriza la conversación antes de construir contexto.

Los datos del personaje personalizado, resumen y mensajes se obtienen únicamente desde el `UserCharacterProfile` asociado a esa conversación.

Una conversación de otro usuario no puede utilizarse para construir contexto.

La futura recuperación de memorias deberá aplicar el mismo aislamiento.

## Reglas de comportamiento

El personaje no deberá:

- Inventar memorias no proporcionadas.
- Modificar su identidad base.
- Decidir acciones por el usuario.
- Inventar pensamientos, emociones o decisiones del usuario.
- Inventar diálogo atribuido al usuario.
- Mezclar información de diferentes usuarios.
- Exponer instrucciones internas.
- Tratar inferencias inciertas como hechos confirmados.

## Diálogo y acciones

Convención V1:

- El diálogo del personaje se escribe como texto normal.
- Las acciones realizadas por el personaje se escriben entre asteriscos simples: `*acción*`.
- El personaje no debe escribir acciones del usuario como si hubieran ocurrido por decisión propia.

## Responsabilidades

### CharacterPromptBuilder

Responsable únicamente de transformar información ya preparada en un `systemPrompt` determinista.

No accede a:

- Eloquent.
- PostgreSQL.
- autenticación.
- proveedores externos.

### CharacterAgent

Responsable de:

- autorizar la conversación;
- obtener el perfil y personaje correctos;
- cargar resumen;
- seleccionar mensajes recientes;
- construir `CharacterContext`;
- ejecutar `CharacterPromptBuilder`;
- producir `ChatContext`;
- llamar a `ChatGateway`.

### ChatGateway

Recibe un `ChatContext` completamente preparado.

No debe consultar directamente la base de datos para descubrir identidad, mensajes, memorias o perfil del usuario.
