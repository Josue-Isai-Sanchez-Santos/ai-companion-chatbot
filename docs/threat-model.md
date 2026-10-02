# Modelo de amenazas

## Estado

**Modelo de amenazas de la versión 1.0.**

Este documento identifica activos, límites de confianza, amenazas, controles y riesgos residuales.

No sustituye una auditoría profesional de seguridad.

## Activos protegidos

El sistema protege:

- cuentas;
- sesiones;
- conversaciones;
- mensajes;
- ramas;
- memorias;
- embeddings;
- personalización;
- estado de relación;
- estado emocional;
- assets privados;
- prompts internos;
- configuración;
- claves de proveedores.

## Límites de confianza

### Datos controlados por la aplicación

Se consideran de mayor confianza:

- reglas globales;
- identidad base;
- reglas del personaje;
- Policies;
- configuración del servidor;
- rutas generadas internamente.

### Datos no confiables

Se consideran entrada no confiable:

- mensajes del usuario;
- memorias;
- resúmenes;
- personalidad personalizada;
- estilo personalizado;
- escenario personalizado;
- contenido generado por modelos;
- metadata recibida de proveedores.

Estos datos no pueden elevar automáticamente su nivel de autoridad.

## Acceso cruzado

### Amenaza

Un usuario intenta consultar o modificar información perteneciente a otro usuario.

### Controles

- autenticación;
-
