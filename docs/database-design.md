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

## Extensión pgvector

AI Companion Chatbot utiliza la extensión `pgvector` de PostgreSQL para almacenar y consultar embeddings asociados a las memorias semánticas.

La extensión se habilita mediante una migración de Laravel utilizando:

```php
Schema::ensureVectorExtensionExists();

Esta operación equivale a ejecutar:

CREATE EXTENSION IF NOT EXISTS vector;

La migración permite preparar una base de datos nueva sin requerir una configuración SQL manual.

En la versión 1.0, pgvector se utilizará para:

Almacenar embeddings de memorias.
Comparar el mensaje actual con recuerdos existentes.
Recuperar únicamente las memorias semánticamente relevantes.
Evitar enviar indiscriminadamente todas las memorias al modelo.

Las dimensiones concretas de los vectores dependerán del modelo de embeddings seleccionado posteriormente.

La extensión se instala antes de crear cualquier tabla que contenga columnas del tipo vector.
