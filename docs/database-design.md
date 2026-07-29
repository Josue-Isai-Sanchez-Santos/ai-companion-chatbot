# Diseño de la base de datos

## Propósito

Este documento describirá las entidades, relaciones, restricciones, índices y decisiones de persistencia utilizadas por AI Companion Chatbot.

## Estado

**Borrador.**

El modelo definitivo será validado durante la creación de las migraciones.

## Alcance de la versión 1.0

La base de datos de la versión 1.0 contemplará principalmente:

- Usuarios.
- Personajes base.
- Expresiones base.
- Perfiles personalizados por usuario.
- Conversaciones.
- Mensajes.
- Memorias.
- Embeddings.
- Eventos de relación.
- Archivos asociados.
- Auditorías de restablecimiento.
- Tablas necesarias para caché, sesiones y colas.

El personaje base permanecerá separado de la información aprendida o personalizada para cada usuario.

Los datos dependientes del perfil personalizado podrán eliminarse mediante relaciones y borrado en cascada durante un restablecimiento.
