# Hoja de ruta

## Estado

**Versión 1.0 completada a nivel funcional.**

Este documento resume el desarrollo realizado y separa la versión terminada de posibles trabajos futuros.

## Principios

La versión 1.0 priorizó:

- experiencia conversacional;
- persistencia;
- aislamiento por usuario;
- memoria;
- continuidad;
- seguridad;
- capacidad de reset;
- pruebas;
- documentación.

No se añadió multimedia antes de completar estos fundamentos.

## Fundamentos

Completado:

- Laravel;
- estructura inicial;
- Git;
- GitHub;
- configuración local;
- `.env.example`;
- PostgreSQL mediante Docker Compose;
- pgvector;
- base de pruebas separada.

## Autenticación

Completado:

- registro;
- login;
- logout;
- Fortify;
- protección de rutas.

## Dominio

Completado:

- enums;
- configuración;
- modelos;
- migraciones;
- factories;
- relaciones.

## Personaje base

Completado:

- `Character`;
- `CharacterExpression`;
- seeder;
- expresión default;
- siete moods/expresiones base.

## Perfil por usuario

Completado:

- `UserCharacterProfile`;
- creación idempotente;
- personalización;
- apodos;
- estado emocional;
- relación.

## Interfaz

Completado:

- Livewire;
- chat;
- estado del personaje;
- controles de conversación;
- gestor de memorias;
- modal de reset.

## Conversaciones

Completado:

- crear;
- seleccionar;
- renombrar;
- eliminar;
- múltiples conversaciones por perfil;
- autorización.

## Persistencia de mensajes

Completado:

- usuario;
- asistente;
- orden cronológico;
- cadena padre-hijo;
- estados;
- persistencia después de recargar.

## Abstracción de IA

Completado:

- `ChatGateway`;
- `EmbeddingGateway`;
- simulación;
- Laravel AI;
- Ollama;
- proveedor externo configurable.

## Contrato de prompts

Completado:

- `CharacterContext`;
- `ChatContext`;
- `CharacterPromptBuilder`;
- orden estable;
- identidad;
- personalización;
- estado;
- memoria;
- resumen;
- protocolo de respuesta.

## Streaming

Completado:

- endpoint;
- SSE;
- placeholder;
- persistencia única;
- estado de fallo;
- estado interrumpido;
- retry.

## Memoria persistente

Completado:

- `Memory`;
- tipos;
- importancia;
- confianza;
- expiración;
- mensaje fuente;
- CRUD manual.

## Memoria semántica

Completado:

- embeddings;
- pgvector;
- similitud;
- ranking;
- límites;
- registro de accesos;
- deduplicación;
- aislamiento.

## Extracción automática

Completado:

- job;
- agente estructurado;
- filtros;
- thresholds;
- embeddings;
- detección de duplicados.

## Resúmenes

Completado:

- threshold;
- resumen acumulativo;
- checkpoint;
- límite de mensajes;
- límite de caracteres;
- inclusión en prompt;
- reducción de historial reciente.

## Relación

Completado:

- `RelationshipEvent`;
- agente de análisis;
- deltas;
- límites backend;
- rangos 0..100;
- etapas;
- idempotencia.

Etapas:

1. strangers
2. acquaintances
3. friends
4. close_friends
5. romantic_interest
6. partners

## Estado emocional

Completado:

- resolver;
- mood;
- expresión;
- integración con eventos de relación;
- fallback neutral.

## Regeneración

Completado:

- regeneración de última respuesta;
- ramas;
- selección activa;
- preservación de alternativa;
- invalidación de resumen contaminado;
- rollback de efectos derivados;
- eliminación de rama inactiva.

## Reset completo

Completado:

- confirmación;
- bloqueo;
- transacción;
- cascadas;
- perfil nuevo;
- conversación nueva;
- auditoría;
- limpieza de assets.

## Infraestructura de assets

Completado para v1.0:

- `GeneratedAsset`;
- tipos;
- propiedad;
- ruta segura;
- storage privado;
- restricciones;
- eliminación durante reset.

La generación multimedia real queda fuera de v1.0.

## Seguridad

Completado:

- Policies;
- aislamiento;
- `InputValidator`;
- `OutputValidator`;
- `ChatSafetyPolicy`;
- rate limiting;
- CSRF;
- rutas privadas;
- protección de secretos;
- modelo de amenazas.

## Pruebas

Completado:

- Unit;
- Feature;
- pruebas de integración de dominio;
- flujo crítico v1;
- cascadas;
- seguridad;
- proveedores simulados;
- pruebas sin secretos reales.

Suite de referencia de cierre de v1.0:

```text
174 tests
638 assertions
```

## Integración continua

Completado:

- GitHub Actions;
- PHP 8.5;
- Node 24;
- Composer;
- npm;
- PostgreSQL + pgvector;
- migraciones;
- build;
- tests;
- APP_KEY de pruebas;
- IA simulada;
- Pint incremental.

## Documentación v1

Incluye:

- README;
- arquitectura;
- base de datos;
- memoria;
- prompts;
- reset;
- roadmap;
- threat model;
- instalación;
- variables;
- proveedor local;
- proveedor externo;
- pruebas;
- capturas.

## Alcance cerrado de v1.0

No incluye:

- generación de imágenes;
- generación de audio;
- generación de video;
- llamadas de voz;
- aplicación móvil;
- pagos;
- monedas virtuales;
- suscripciones;
- marketplace;
- personajes públicos;
- comunidad;
- fine-tuning;
- entrenamiento de un LLM propio.

## Posibles tareas de mantenimiento

Sin ampliar necesariamente el producto:

- aplicar Pint al baseline histórico completo;
- revisar periódicamente advisories de Composer/npm;
- ampliar observabilidad;
- añadir métricas operativas;
- mejorar cobertura de fallos de infraestructura;
- añadir estrategia de backups;
- añadir limpieza reintentable de assets huérfanos.

## Posible v1.1

Opciones para evaluar:

- múltiples personajes seleccionables;
- mejor UI de personalización;
- exportación de conversaciones;
- exportación de memorias;
- administración de perfiles;
- historial visual de ramas;
- métricas de uso;
- búsqueda avanzada de conversaciones.

## Posible v2

Solo después de validar el núcleo conversacional:

- generación de imágenes;
- audio;
- voz;
- video;
- entrega autorizada de assets privados;
- almacenamiento S3 compatible;
- multimodalidad.

## Regla para ampliar el proyecto

Cada función nueva debe conservar:

```text
aislamiento por usuario
autorización
consistencia transaccional
capacidad de reset
protección de secretos
pruebas automatizadas
documentación
```

Una función no debe considerarse terminada si rompe alguno de esos invariantes.
