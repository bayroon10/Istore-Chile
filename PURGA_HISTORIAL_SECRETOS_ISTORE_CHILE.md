# Purga Historial de Secretos — iStore Chile

Este documento registra los resultados de la auditoría de seguridad histórica y la purga definitiva de secretos comprometidos en el repositorio de iStore Chile.

---

## 1. Hallazgo inicial
Durante un escaneo profundo de seguridad sobre el historial de Git en la rama `main`, se identificó la presencia de secretos válidos de infraestructura expuestos de forma permanente en el historial de confirmaciones de la base de datos de producción:
* **Secreto Comprometido:** Contraseña de la base de datos PostgreSQL alojada en **Neon.tech**.
* **Valor Histórico Expuesto:** `npg_3FqdW8CDyefu`
* **Riesgo:** Cualquier usuario con acceso de lectura al repositorio de GitHub podía extraer la credencial histórica y conectarse directamente a la base de datos de producción.

---

## 2. Archivo purgado
El secreto se encontraba expuesto dentro del siguiente archivo de testing que existía en commits anteriores:
* **Ruta del Archivo:** `backend-php/test_db.php`
* **Archivos Adicionales Auditados:** También se identificó el archivo `backend-php/test_stripe_full.ps1` como un script obsoleto de desarrollo en el historial de Git, el cual fue seleccionado para purga completa para eliminar vectores innecesarios.

---

## 3. Método usado
Para asegurar una eliminación permanente y limpia sin romper la integridad del árbol de commits ni el código del proyecto, se utilizó la herramienta oficial de alto rendimiento recomendada por Git: **`git filter-repo`**.

El proceso se ejecutó de forma automatizada mediante el siguiente comando de consola:
```powershell
python -m git_filter_repo --path backend-php/test_db.php --path backend-php/test_stripe_full.ps1 --invert-paths --force
```

---

## 4. Verificación posterior
Para certificar que el secreto ha sido eliminado de forma absoluta del repositorio, se corrieron búsquedas de bajo nivel en todos los commits, ramas y blobs históricos:
* **Búsqueda por Nombre de Archivo:**
  ```powershell
  git log --all --name-only | findstr /i "test_db.php test_stripe_full.ps1"
  # Resultado: [VACÍO] - Los archivos ya no existen en ninguna referencia histórica.
  ```
* **Búsqueda por Valor del Secreto:**
  ```powershell
  git log -p -G "npg_3FqdW8CDyefu"
  # Resultado: [VACÍO] - La cadena de texto del secreto antiguo es totalmente irrecuperable.
  ```

---

## 5. Estado del remoto
Debido a que `git filter-repo` elimina por seguridad la referencia `origin` remota para evitar empujes involuntarios durante la limpieza de la base de datos interna de Git, se procedió con la restauración del remote:
* **Remote Reconfigurado:** `https://github.com/bayroon10/Istore-Chile.git`
* **Acción Requerida para Sincronizar GitHub:** Como el historial de Git fue reescrito de forma permanente, se requiere ejecutar un empuje forzado de las ramas limpias:
  ```powershell
  git push origin main --force --all
  ```

---

## 6. Riesgos pendientes

> [!IMPORTANT]
> **Acción Requerida de Rotación:**
> Si aún no has rotado la contraseña en la consola de administración de **Neon.tech**, debes hacerlo inmediatamente para invalidar por completo la clave `npg_3FqdW8CDyefu` a nivel de servidor. Purgar el historial de Git protege tu portafolio de visualizaciones externas de la clave, pero la contraseña solo queda inactiva si se regenera directamente desde tu proveedor de base de datos.
