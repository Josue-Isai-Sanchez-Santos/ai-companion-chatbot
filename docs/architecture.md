# Arquitectura del sistema

## Estado

**Implementado para la versión 1.0.**

AI Companion Chatbot utiliza un monolito modular basado en Laravel.

La aplicación concentra autenticación, dominio, persistencia, autorización, integración con IA y procesamiento asíncrono dentro del mismo proyecto, pero mantiene responsabilidades separadas mediante Actions, Agents, Gateways, Jobs, Policies y servicios especializados.

## Componentes principales
