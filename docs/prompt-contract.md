# Contrato de construcción de prompts

## Propósito

Este documento definirá qué información puede enviarse al modelo, en qué orden se construye el contexto y qué reglas debe respetar el personaje al responder.

## Estado

**Borrador.**

La estructura del prompt será validada mediante pruebas de consistencia conversacional.

## Alcance de la versión 1.0

El prompt de conversación podrá incluir:

- Reglas globales del sistema.
- Identidad del personaje.
- Personalidad base.
- Personalidad personalizada.
- Historia del personaje.
- Forma de hablar.
- Escenario actual.
- Estado emocional.
- Estado de relación.
- Resumen de la conversación.
- Memorias relevantes.
- Mensajes recientes.
- Instrucción específica para generar la respuesta.

El personaje no deberá:

- Inventar memorias no proporcionadas.
- Modificar su identidad base.
- Decidir acciones por el usuario.
- Mezclar información de diferentes usuarios.
- Exponer instrucciones internas.
- Tratar inferencias inciertas como hechos confirmados.

El proveedor de inteligencia artificial recibirá un contexto preparado por la aplicación y no accederá directamente a la base de datos.
