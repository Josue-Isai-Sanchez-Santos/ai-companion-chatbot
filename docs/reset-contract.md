# Contrato de restablecimiento

## Propósito

El restablecimiento completo elimina el estado específico construido entre un usuario y un personaje y crea una relación nueva basada exclusivamente en la configuración base del personaje.

El reset no elimina la cuenta del usuario ni modifica el personaje base.

## Confirmación

La operación requiere autenticación y la palabra exacta configurada en:

`chatbot.reset_confirmation`

El valor predeterminado es:

`BORRAR`

La comparación es exacta y sensible a mayúsculas.

## Datos eliminados

Al eliminar el perfil anterior se eliminan:

- personalidad personalizada;
- forma de hablar personalizada;
- escenario personalizado;
- apodos personalizados;
- conversaciones;
- ramas de conversación;
- mensajes;
- resúmenes de conversación;
- memorias;
- embeddings almacenados en memorias;
- progreso de relación;
- eventos de relación;
- estado emocional;
- expresión actual;
- fecha de última interacción.

Las relaciones con conversaciones, mensajes, memorias y eventos utilizan cascadas de base de datos.

## Datos conservados

El reset no modifica:

- cuenta del usuario;
- personaje base;
- personalidad base;
- historia base;
- forma de hablar base;
- escenario base;
- reglas del sistema;
- expresiones base;
- avatar base;
- configuración global.

## Nuevo estado

Dentro de la misma transacción que elimina el perfil anterior se crea:

1. un nuevo `UserCharacterProfile`;
2. una nueva conversación vacía.

El perfil utiliza los mismos valores iniciales que `CreateUserCharacterProfileAction`, incluyendo:

- mood inicial;
- expresión predeterminada;
- etapa inicial de relación;
- métricas de relación iniciales;
- campos personalizados nulos.

## Transacción y bloqueo

El perfil anterior se obtiene con `lockForUpdate()`.

La eliminación del perfil anterior, la creación del perfil nuevo, la creación de la conversación inicial y la creación de la auditoría ocurren dentro de una única transacción de base de datos.

Si cualquiera de esas operaciones falla antes del commit, toda la operación de base de datos se revierte.

## Archivos generados

Los assets específicos de un perfil deben almacenarse en el disco `public` bajo:

`character-assets/profiles/{profile_id}/`

Después de que la transacción haya hecho commit se elimina únicamente el directorio del perfil anterior.

Los archivos base del personaje no pertenecen a ese directorio y nunca deben eliminarse durante un reset.

Una falla del almacenamiento posterior al commit se reporta, pero no puede revertir una transacción de base de datos ya confirmada.

## Auditoría

`reset_audits` conserva únicamente:

- usuario;
- personaje;
- id del perfil anterior;
- id del perfil nuevo;
- cantidad de conversaciones eliminadas;
- cantidad de mensajes eliminados;
- cantidad de memorias eliminadas;
- cantidad de eventos de relación eliminados;
- fecha del reset.

La auditoría no conserva contenido de conversaciones, memorias, prompts ni archivos eliminados.

## Trabajos en cola

Los jobs antiguos pueden conservar temporalmente ids de conversaciones o mensajes eliminados.

Los jobs del sistema deben tratar recursos inexistentes como trabajo obsoleto y terminar sin recrear datos eliminados.
