# Sistema de memoria

## Propósito

Este documento describirá cómo el chatbot almacena, selecciona, recupera, actualiza y elimina información relevante de las conversaciones.

## Estado

**Borrador.**

Los criterios exactos de similitud, importancia y confianza se ajustarán mediante pruebas.

## Alcance de la versión 1.0

La versión 1.0 contemplará:

- Contexto inmediato basado en mensajes recientes.
- Resúmenes de conversaciones largas.
- Memorias persistentes.
- Embeddings almacenados con pgvector.
- Recuperación semántica de recuerdos relevantes.
- Clasificación por tipo, importancia y confianza.
- Detección y reducción de memorias duplicadas.
- Memorias temporales con posible fecha de expiración.
- Interfaz para que el usuario consulte, modifique y elimine recuerdos.

El sistema no guardará automáticamente cada mensaje como una memoria permanente.

La memoria será independiente del entrenamiento del modelo y se eliminará durante el restablecimiento completo del personaje.
