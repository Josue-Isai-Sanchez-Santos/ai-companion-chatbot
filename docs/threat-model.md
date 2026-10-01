# Modelo de amenazas

## Propósito

Este documento describe los límites de confianza, amenazas conocidas, controles implementados y riesgos residuales del sistema de compañero conversacional.

No sustituye una auditoría profesional de seguridad.

## Activos protegidos

El sistema protege principalmente:

- cuentas de usuario;
- conversaciones y mensajes;
- ramas de mensajes;
- memorias y embeddings;
- progreso y eventos de relación;
- personalización del personaje;
- estado emocional;
- assets generados;
- prompts internos;
- configuración de proveedores;
- claves de API.

## Límites de confianza

### Datos confiables del sistema

Se consideran configuración controlada por la aplicación:

- reglas globales del prompt;
- personaje base;
- system rules;
- políticas de autorización;
- configuración del servidor.

### Datos no confiables

Se consideran datos controlables directa o indirectamente por el usuario:

- mensajes;
- personalidad personalizada;
- forma de hablar personalizada;
- escenario personalizado;
- resúmenes generados;
- memorias;
- contenido recuperado semánticamente;
- nombres y contenido de conversaciones.

Estos datos nunca deben reemplazar las reglas globales del sistema.

## Autorización y aislamiento

Las conversaciones y memorias se autorizan mediante policies que comprueban que el perfil pertenece al usuario autenticado.

Los assets generados se autorizan a través de su `UserCharacterProfile`.

Los usuarios normales pueden consultar el personaje base activo pero no modificarlo ni eliminarlo.

El reset comprueba explícitamente que el perfil pertenece al usuario autenticado.

Las pruebas de autorización incluyen intentos reales de acceso cruzado que deben producir HTTP 403.

## CSRF

Las operaciones mutables se encuentran bajo rutas web autenticadas.

Laravel aplica la protección CSRF del grupo `web`.

Los formularios HTML utilizan `@csrf`.

El cliente de streaming envía el token CSRF mediante la cabecera `X-CSRF-TOKEN`.

No deben agregarse endpoints mutables al middleware de exclusión CSRF sin una justificación específica.

## Validación de entradas

Los mensajes:

- deben ser texto;
- no pueden estar vacíos;
- tienen longitud máxima configurable;
- deben ser UTF-8 válido;
- no pueden contener bytes NUL ni controles ASCII peligrosos.

No se utiliza una lista negra de frases de prompt injection.

Frases como “ignora instrucciones anteriores” pueden ser conversación legítima y son tratadas como datos, no como instrucciones de sistema.

## Prompt injection

Los mensajes, memorias, resúmenes y personalización son considerados datos no confiables.

El prompt global establece explícitamente que:

- no pueden reemplazar `GLOBAL_RULES`;
- no pueden reemplazar `CHARACTER_RULES`;
- no pueden redefinir la jerarquía de instrucciones;
- no pueden ordenar la revelación del prompt interno.

Este control reduce el riesgo, pero ningún prompt puede garantizar por sí solo resistencia absoluta a prompt injection.

## Validación de salida

Las respuestas del personaje se validan antes de persistirse.

Durante streaming se valida el contenido acumulado antes de enviar cada nuevo delta.

Se rechazan:

- salidas vacías finales;
- UTF-8 inválido;
- controles ASCII peligrosos;
- respuestas que excedan la longitud máxima;
- marcadores internos conocidos del prompt.

La detección de marcadores no garantiza detectar toda posible paráfrasis de instrucciones internas y se considera defensa en profundidad.

## Cross-site scripting

Los mensajes se renderizan mediante escaping de Blade o mediante `textContent` en JavaScript.

El sistema no interpreta mensajes del usuario ni respuestas del modelo como HTML ejecutable.

## Mass assignment

Las acciones reciben listas explícitas de campos.

Los atributos sensibles no deben poblarse directamente desde `$request->all()`.

En particular, `GeneratedAsset::path` no es fillable y se genera internamente.

## Assets y rutas

Los assets generados permanecen en almacenamiento privado bajo:

`storage/app/private/character-assets/profiles/{profile_id}/`

La ruta se genera mediante el id del perfil y un ULID.

No se aceptan rutas ni nombres físicos proporcionados por el usuario.

La base de datos aplica una restricción adicional para evitar rutas fuera del directorio esperado y secuencias `..`.

No existe una URL pública directa para assets generados.

## Claves y secretos

Las claves se cargan mediante variables de entorno.

Los archivos `.env` y sus variantes privadas están ignorados por Git.

Los archivos de ejemplo contienen solamente placeholders.

Las claves nunca deben:

- almacenarse en tablas;
- incluirse en prompts;
- devolverse al cliente;
- incluirse en commits;
- registrarse deliberadamente en logs.

## Logging y privacidad

La aplicación no registra deliberadamente:

- mensajes completos;
- conversaciones completas;
- prompts del sistema;
- memorias completas;
- claves de API.

Los errores recuperables de los gateways se convierten en excepciones genéricas y no conservan la excepción original del proveedor como cadena interna.

Los errores inesperados pueden conservar información técnica suficiente para diagnóstico, pero el código de aplicación no debe adjuntar contenido conversacional a esos logs.

## Rate limiting

La generación de respuestas tiene un límite configurable por usuario y minuto.

El límite también aplica a reintentos y regeneraciones.

El reset dispone de un límite independiente por usuario y hora mediante middleware.

El rate limiting reduce abuso y consumo accidental, pero no sustituye cuotas del proveedor ni controles de infraestructura.

## Eliminación de datos

El reset completo:

- bloquea el perfil;
- utiliza una transacción;
- elimina el perfil anterior;
- usa cascadas para conversaciones, mensajes, memorias, embeddings, eventos y assets;
- crea un perfil nuevo;
- conserva una auditoría mínima;
- elimina assets físicos después del commit.

Si la transacción de base de datos falla antes del commit, se revierte.

Los archivos no se eliminan antes del commit.

## Trabajos en cola

Los jobs pueden contener identificadores de recursos eliminados.

Los jobs deben comprobar que sus conversaciones, mensajes o perfiles todavía existen antes de modificar datos.

Un job obsoleto debe terminar sin reconstruir información eliminada.

## Amenazas principales cubiertas

1. Acceso cruzado entre usuarios.
2. Modificación del personaje base.
3. Prompt injection.
4. Fuga evidente de instrucciones internas.
5. Manipulación de rutas.
6. Mass assignment inseguro.
7. CSRF en operaciones web.
8. Abuso de generación mediante solicitudes repetidas.
9. Eliminación parcial durante reset.
10. Exposición accidental de secretos.
11. Persistencia innecesaria de información sensible en logs.

## Riesgos residuales

Persisten riesgos que requieren capas externas o futuras:

- prompt injection no detectable mediante reglas estáticas;
- vulnerabilidades de dependencias;
- compromiso del proveedor de IA;
- compromiso del host o base de datos;
- robo de sesión;
- abuso distribuido desde múltiples cuentas;
- filtraciones debidas a operadores o infraestructura;
- archivos maliciosos cuando en el futuro se implemente carga o generación multimedia.

Cada nueva función de archivos, audio, imágenes o herramientas deberá ampliar este modelo de amenazas.
