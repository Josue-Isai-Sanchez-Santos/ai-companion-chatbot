# Contrato de restablecimiento

## Propósito

Este documento definirá el comportamiento, alcance, protecciones y garantías del restablecimiento completo de un personaje.

## Estado

**Borrador.**

La implementación final será validada mediante pruebas de transacciones, autorización y borrado en cascada.

## Alcance de la versión 1.0

El restablecimiento eliminará para el usuario y personaje seleccionados:

- Personalidad personalizada.
- Forma de hablar personalizada.
- Conversaciones.
- Mensajes.
- Resúmenes.
- Memorias.
- Embeddings.
- Progreso de relación.
- Eventos de relación.
- Estado emocional.
- Expresión actual.
- Escenario personalizado.
- Archivos generados o asociados.

El restablecimiento conservará:

- Cuenta del usuario.
- Personaje base.
- Personalidad base.
- Historia base.
- Expresiones base.
- Avatar base.
- Configuración global.
- Registro mínimo de auditoría.

La operación requerirá una confirmación explícita mediante la palabra `BORRAR`.

Después del restablecimiento se creará un perfil limpio basado en la configuración original del personaje.
