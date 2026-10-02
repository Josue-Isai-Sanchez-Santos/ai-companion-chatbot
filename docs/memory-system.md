# Sistema de memoria

## Estado

**Implementado para la versión 1.0.**

El sistema utiliza memoria explícita, embeddings y recuperación semántica para proporcionar continuidad sin enviar indiscriminadamente todo el historial al modelo.

## Objetivos

La memoria debe:

- permanecer aislada por usuario y personaje;
- almacenar únicamente información suficientemente estable;
- recuperar solo información relevante;
- permitir edición y eliminación manual;
- evitar duplicados semánticos;
- excluir información expirada;
- desaparecer durante un reset completo.

## Tipos de memoria

La aplicación contempla tipos como:
