# Arquitectura del sistema

## Propósito

Este documento describirá la arquitectura general de AI Companion Chatbot, sus módulos internos, responsabilidades, dependencias y flujo principal de información.

## Estado

**Borrador.**

La arquitectura todavía puede cambiar durante las primeras etapas de implementación.

## Alcance de la versión 1.0

La versión 1.0 utilizará una arquitectura de monolito modular basada en Laravel.

La arquitectura contemplará:

- Aplicación web desarrollada con Laravel.
- Interfaz construida con Blade, Livewire y Alpine.js.
- PostgreSQL como base de datos principal.
- pgvector para almacenar y consultar embeddings.
- Módulos separados para conversación, personajes, memoria, relación y restablecimiento.
- Procesamiento asíncrono para tareas secundarias.
- Una abstracción para cambiar entre proveedor externo y modelo local.
- Autorización y aislamiento de información por usuario.

No se implementarán microservicios, aplicación móvil, multimedia, pagos ni marketplace en la versión 1.0.
