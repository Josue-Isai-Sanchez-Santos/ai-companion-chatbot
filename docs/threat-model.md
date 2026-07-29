# Modelo de amenazas

## Propósito

Este documento identificará los principales riesgos de seguridad, privacidad, autorización e integridad de datos del sistema.

## Estado

**Borrador.**

Los riesgos y controles serán ampliados conforme se implementen los distintos módulos.

## Alcance de la versión 1.0

El análisis inicial contemplará:

- Acceso a conversaciones de otros usuarios.
- Acceso a memorias de otros perfiles.
- Modificación no autorizada de personajes.
- Exposición de claves de proveedores.
- Filtración de prompts internos.
- Inyección de instrucciones mediante mensajes.
- Almacenamiento excesivo de información privada.
- Registros que contengan conversaciones sensibles.
- Manipulación de rutas de archivos.
- Borrados accidentales o no autorizados.
- Estados inconsistentes durante un restablecimiento.
- Uso excesivo de recursos o llamadas al proveedor.
- Respuestas incompletas o dañadas por interrupciones.
- Datos residuales después de eliminar memorias o conversaciones.

La versión 1.0 aplicará autenticación, políticas de autorización, validación de entradas, aislamiento por usuario, transacciones, protección de secretos y pruebas automatizadas.

Este documento no sustituye una auditoría profesional de seguridad.
