# Postmortem: Corrupción de booleanos + fallo de prepared statements con PgBouncer

**Fecha:** Agosto 2026  
**Severidad:** Alta (corrupción silenciosa de datos en producción)  
**Estado:** Resuelto  

## Qué pasó

La app estaba corrompiendo valores booleanos al leerlos/escribirlos contra Postgres en producción (Neon). No tiraba un error visible tipo 500 — los datos simplemente se guardaban o leían mal, que es peor porque no se nota hasta que alguien lo pesca en el negocio.

## Causa raíz

El problema tenía dos capas, no una:

1. La configuración de PDO tenía `ATTR_EMULATE_PREPARES => true`. Esto hace que PHP simule los prepared statements en el cliente en vez de dejar que Postgres los maneje de forma nativa. Al emular, se pierde precisión de tipo, y ahí es donde los booleanos se rompían.
2. El fix obvio — poner `ATTR_EMULATE_PREPARES => false` para que Postgres maneje los prepares de forma nativa — arregló los booleanos pero rompió otra cosa: chocaba con **PgBouncer en modo transaction pooling** (que es como Neon maneja las conexiones). En ese modo, las conexiones se reciclan entre transacciones distintas, y los prepared statements nativos no sobreviven ese reciclaje.

## Por qué costó encontrarla

Porque parecían dos bugs distintos en vez de uno con dos síntomas. Arreglar la primera capa (booleanos) hizo aparecer la segunda (pooling), lo cual podía leerse como "rompí algo al arreglar otra cosa" en vez de "esto es la misma causa raíz mostrando otra cara".

## Cómo se confirmó

- Se exigió log literal en cada paso del diagnóstico, nunca conclusiones sin evidencia.
- Se corrió `grep` de impacto global antes de aplicar cualquier fix, para saber qué más tocaba ese cambio de configuración.
- La suite completa se corrió contra Postgres real (no SQLite), porque el bug solo aparece con Postgres + pooling real — en SQLite nunca se hubiera visto.
- Se validó con una ráfaga de 10/10 requests exitosas en producción como prueba final de estabilidad.

## Fix final

`PDO::PGSQL_ATTR_DISABLE_PREPARES => true`

Esta opción es específica de Postgres y elimina los prepared statements por completo (ni emulados ni nativos), mandando el SQL directo. Resuelve las dos capas de una sola vez: no hay emulación que rompa tipos, y no hay prepared statement nativo que PgBouncer pueda invalidar al reciclar la conexión.

## Qué cambia a futuro

- Cualquier cambio de configuración de PDO/DB de acá en adelante se valida contra Postgres real con pooling real antes de darlo por cerrado — SQLite no es representativo de este tipo de bug.
- Cuando un fix "arregla A pero rompe B", la pregunta correcta no es "¿qué rompí ahora?" sino "¿qué estoy asumiendo del entorno que no es cierto?". Acá la asunción rota fue que prepared statements nativos se comportan igual con o sin pooling.
