# AI Companion Chatbot

Aplicación web para conversar con un personaje ficticio que conserva personalidad, contexto, memorias y progreso de relación.

## Objetivo

Desarrollar un chatbot conversacional con continuidad narrativa y memoria persistente. El sistema permitirá que cada usuario tenga una versión personalizada del personaje, sin modificar la definición original compartida por la aplicación.

Este proyecto no pretende recrear ni clonar OurDream.ai. Únicamente toma como referencia el concepto general de conversar con un personaje persistente. No incluirá la plataforma social, el sistema comercial ni las funciones multimedia de ese servicio.

## Estado del proyecto

**Estado actual:** desarrollo inicial.

Actualmente se encuentra completada la instalación base de Laravel. La arquitectura, la base de datos, la autenticación y los módulos conversacionales todavía están en proceso de implementación.

## Tecnologías previstas

- PHP 8.5
- Laravel 13
- Blade
- Livewire
- Alpine.js
- PostgreSQL
- pgvector
- Docker Compose
- Laravel Queue
- Vite
- PHPUnit
- Proveedor externo de inteligencia artificial
- Modelo local compatible mediante una abstracción de proveedor

Algunas tecnologías todavía no están instaladas. Esta lista representa la arquitectura prevista para la versión 1.0.

## Alcance de la versión 1.0

La primera versión incluirá:

- Registro e inicio de sesión.
- Un personaje base.
- Un perfil personalizado del personaje por usuario.
- Conversaciones e historial persistente.
- Personalidad y forma de hablar configurables.
- Expresiones y estado emocional.
- Memoria semántica.
- Resúmenes de conversaciones.
- Estado y progreso de relación.
- Respuestas generadas progresivamente.
- Regeneración de respuestas.
- Restablecimiento completo del personaje.
- Cambio entre proveedor externo y modelo local.
- Pruebas automatizadas.
- Documentación técnica.

## Fuera del alcance de la versión 1.0

La primera versión no incluirá:

- Generación de imágenes.
- Generación o reproducción de voz.
- Generación de video.
- Pagos.
- Monedas virtuales.
- Suscripciones.
- Aplicación móvil.
- Comunidad de usuarios.
- Personajes públicos.
- Marketplace.
- Fine-tuning de modelos.
- Entrenamiento de un modelo de lenguaje propio.

Estas funciones solo podrán evaluarse después de completar y validar la experiencia conversacional de la versión 1.0.

## Principios de diseño

- El personaje base no será modificado por las conversaciones.
- Cada usuario tendrá un perfil independiente del personaje.
- El proveedor de inteligencia artificial no accederá directamente a la base de datos.
- Las memorias serán seleccionadas según relevancia, no enviadas indiscriminadamente.
- El usuario podrá revisar y eliminar sus memorias.
- El restablecimiento eliminará la evolución del personaje, pero conservará la cuenta y la definición original.
- Las claves, contraseñas y archivos `.env` no serán almacenados en Git.

## Estructura documental

La documentación técnica se encuentra en `docs/`:

- `architecture.md`: arquitectura general.
- `database-design.md`: diseño de la base de datos.
- `memory-system.md`: funcionamiento de la memoria.
- `prompt-contract.md`: contrato de construcción de prompts.
- `reset-contract.md`: comportamiento del restablecimiento.
- `roadmap.md`: orden de desarrollo.
- `threat-model.md`: riesgos y controles de seguridad.

## Desarrollo local

El proyecto se desarrolla dentro de Ubuntu mediante WSL.

```bash
cd ~/proyectos/ai-companion-chatbot
php artisan serve

Para ejecutar Vite durante el desarrollo:

npm run dev
Pruebas
php artisan test```
