# Guía de Instalación · Sistema Préstamos Huacachin

Documento de referencia para **instalar desde cero** y **re-importar datos legacy**.
Última actualización: 2026-08-09.

---

## Tabla de contenidos

- [1. Requisitos](#1-requisitos)
- [2. Instalación inicial (primera vez)](#2-instalación-inicial-primera-vez)
- [3. Re-importación de datos legacy](#3-re-importación-de-datos-legacy)
- [4. Comandos artisan disponibles](#4-comandos-artisan-disponibles)
- [5. Estructura de tablas críticas](#5-estructura-de-tablas-críticas)
- [6. Notas para el script de importación](#6-notas-para-el-script-de-importación)
- [7. Troubleshooting](#7-troubleshooting)
- [8. Refresh de producción desde el legacy (runbook)](#8-refresh-de-producción-desde-el-legacy-runbook--probado-2026-08-07-y-2026-08-09)

---

## 1. Requisitos

| Componente | Versión |
|---|---|
| PHP | 8.4+ |
| MySQL / MariaDB | 8.0+ / 10.5+ |
| Composer | 2.x |
| Node.js | 20+ |
| Extensiones PHP | `gd`, `mbstring`, `pdo_mysql`, `openssl`, `tokenizer`, `xml`, `bcmath`, `fileinfo` |

---

## 2. Instalación inicial (primera vez)

```bash
# 1. Clonar el repositorio
git clone https://github.com/huacachin/prestamoh.git
cd prestamoh

# 2. Dependencias
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate
# Editar .env: DB_*, APP_URL, MIGO_PE_TOKEN

# 4. Migrar BD
php artisan migrate --force

# 5. Cargar seeders base (roles, permisos, conceptos, sucursal, superadmin)
php artisan db:seed --force

# 6. Symlink storage
php artisan storage:link

# 7. Cachear configuración para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Health check
php artisan installation:check
```

Si el health check pasa OK, la instalación está lista. Acceder con el SuperUsuario creado por el seeder.

---

## 3. Re-importación de datos legacy

> Aplicar este flujo cuando se reciba un nuevo dump del sistema legacy (PHP+MySQL viejo).

### 3.1 Pre-importación (limpiar estado actual)

```bash
# Opción A — empezar limpio (CUIDADO: borra TODO menos roles/permisos)
php artisan migrate:fresh --force --seed

# Opción B — mantener roles/permisos, solo borrar data
# (truncate de tablas de data manualmente)
```

### 3.2 Importar datos legacy

Ejecutar **tu script de importación** (externo) que mapee:

| Legacy | Laravel |
|---|---|
| `huaca_persona` | `clients` |
| `huaca_clienteaval` | `client_avales` |
| `huaca_cliente_ima` | `client_attachments` (+ copia archivos físicos a `storage/app/public/clients/{id}/`) — `legacy:migrate --step=attachments`; ingresos/egresos mapean POSICIONALMENTE (identrada→id nuevo) |
| `huaca_cab_cuentacorriente` | `credits` |
| `huaca_det_cuentacorriente` | `credit_installments` |
| `huaca_ingreso` | `payments` (cuando `modo=CREDITO`) + `incomes` (cuando `modo=Fijos/Otros`) |
| `huaca_ingreso3` | `incomes` con `caja=3` |
| `huaca_entrada` | `expenses` con `caja=1` |
| `huaca_entrada3` | `expenses` con `caja=3` |
| `huaca_62a_ima2` | `expense_attachments` (+ archivos a `storage/app/public/expenses/{id}/`) |
| `huaca_62a_ima3` | `income_attachments` (+ archivos a `storage/app/public/incomes/{id}/`) |
| `huaca_cab_masivo` | `mass_deletions` |
| `huaca_det_masivo` | `mass_deletion_details` |
| `huaca_caja` | `cash_openings` |
| `huaca_diasmora` | `dias_mora` |
| `huaca_moraacum` | `mora_acumulada` |
| `huaca_clientes` (= usuarios legacy) | `users` (con roles Spatie) |
| `huaca_webparametros` ccodparametro=0012 | `concepts` |

### 3.3 Post-importación (saneamiento OBLIGATORIO)

**1 solo comando que arregla todo:**

```bash
php artisan installation:run-all
```

O paso por paso (todos soportan `--dry-run` para previsualizar):

```bash
php artisan installation:fix-invalid-dates       # 0000-00-00 → NULL en payments/credits
php artisan installation:fix-expense-documento   # expenses sin documento → 'GUIA'
php artisan installation:sync-correlativos       # correlativos.correl = MAX(id)
php artisan mass-deletions:fix-amounts           # recálculo amount = sum(details)
php artisan installation:migrate-roles           # roles legacy → nuevos (idempotente)
php artisan installation:fix-autoincrement       # AUTO_INCREMENT = MAX(id)+1
php artisan installation:check                   # verificación final
```

### 3.4 Cachés y verificación final

```bash
php artisan optimize:clear
php artisan optimize
php artisan installation:check  # debe salir TODO ✓
```

---

## 4. Comandos artisan disponibles

### Comandos de instalación / saneamiento

| Comando | Qué hace | Modo dry-run |
|---|---|---|
| `installation:check` | Health check completo (read-only) | siempre lectura |
| `installation:run-all` | Ejecuta todo el saneamiento en orden | `--no-interaction` para CI |
| `installation:fix-invalid-dates` | Convierte fechas `0000-00-00` a NULL | `--dry-run` |
| `installation:fix-expense-documento` | Pone `documento='GUIA'` donde NULL | `--dry-run` |
| `installation:sync-correlativos` | Sincroniza correlativos al MAX(id) | `--dry-run` |
| `installation:fix-autoincrement` | Resetea AUTO_INCREMENT | `--dry-run` |
| `mass-deletions:fix-amounts` | Recalcula `amount` = sum(details) | `--dry-run` |
| `installation:migrate-roles` | Mapea roles legacy → nuevos y los borra | `--dry-run` |

### Comandos nativos de Laravel relevantes

```bash
php artisan migrate                  # aplicar migraciones
php artisan migrate:fresh --seed     # reset BD + seeders
php artisan storage:link             # symlink public/storage
php artisan optimize                 # cachear config/routes/views
php artisan optimize:clear           # limpiar todos los cachés
php artisan db:seed                  # ejecutar seeders
```

---

## 5. Estructura de tablas críticas

### Diferencias clave vs legacy

| Cambio | Laravel | Legacy |
|---|---|---|
| Mora capital y mora interés separadas en cuotas | `credit_installments.importe_mora` + `credit_installments.mora_interes` | `huaca_det_cuentacorriente.impomora` + `impomorai` |
| Galería múltiple por cliente | tabla nueva `client_attachments` | `huaca_cliente_ima` |
| Galería múltiple por ingreso/egreso | `income_attachments` + `expense_attachments` | `huaca_62a_ima3` + `huaca_62a_ima2` |
| Reserva mora separada | `mora_acumulada` | `huaca_moraacum` |
| Bitácora días mora | `dias_mora` | `huaca_diasmora` |
| Avales | `client_avales` | `huaca_clienteaval` |
| Correlativos | `correlativos` (tipo, correl) | `huaca_tabla` con tipo |
| Auditoría de pagos masivos | `mass_deletions` + `mass_deletion_details` (con tipo C/I/M) | `huaca_cab_masivo` + `huaca_det_masivo` |

### Convenciones nuevas

- **`incomes.modo`** y **`expenses.modo`** = 'Fijos' | 'Otros'
- **`incomes.documento`** y **`expenses.documento`** = 'GUIA' (default)
- **`incomes.caja`** y **`expenses.caja`** = 1 (caja principal) o 3 (caja 3 / Otros mov. para reportes)
- **`payments.fecha`** es nullable (acepta NULL para datos huérfanos legacy)
- **`mass_deletions.amount`** = SOLO lo cobrado (NO incluye mora reservada)

---

## 6. Notas para el script de importación

> Punto de atención por cada tabla durante la importación:

### `clients`
- Mapear `huaca_persona.cdnipersona` → `clients.documento`
- Mapear `huaca_persona.ccodpersona` → `clients.expediente`
- Default `tipo_documento='DNI'` si no viene
- `email` puede ser hardcoded `g@huacachin.com` (mismo legacy)

### `credits`
- `mora1` = `huaca_cab.mora1` (Pago x día)
- `mora2` = `huaca_cab.mora2` (Mora interés)
- `tipo_planilla`: 1=Sem, 3=Mens, 4=Diar
- `situacion`: 'Activo' | 'Cancelado' | 'Refinanciado' | 'Eliminado'
- `cod_rem`: 'REF' para refinanciados
- `idcan`: id del crédito original cuando es refinanciamiento

### `credit_installments`
- **CRÍTICO**: importar `huaca_det_cuentacorriente.impomora` → `importe_mora` y **`impomorai` → `mora_interes`** (columna nueva)
- Sin esto, los reportes de mora interés vs capital van a estar mal

### `payments`
- Si `huaca_ingreso.fechaentrada = '0000-00-00'`, importar como NULL
- O importar tal cual y después correr `installation:fix-invalid-dates`

### `incomes` / `expenses`
- Si `caja=1` viene del legacy, va a `caja=1` (caja principal)
- Si `caja=3`, va a `caja=3` (otros movimientos)
- Si NULL, después correr `installation:fix-expense-documento` (default GUIA)

### `mass_deletions`
- El legacy guardaba `cab_masivo.monto = monto + mora` aunque la mora se reservara
- **Después de importar SIEMPRE ejecutar `mass-deletions:fix-amounts`** para corregir

### Imágenes / archivos
- Copiar **archivos físicos** del legacy a:
  - `storage/app/public/clients/{client_id}/` (originales)
  - `storage/app/public/clients/{client_id}/thumbs/` (miniaturas)
  - Idem para `incomes/` y `expenses/`
- Después poblar la tabla `*_attachments` con los paths relativos
- Si no se generan thumbs, el modelo cae al original automáticamente

### `mora_acumulada`
- Importar `huaca_moraacum.importe` y `huaca_moraacum.dias`
- Si está vacía, no pasa nada — se va llenando con uso (Reserva Mora)

### `correlativos`
- Si la importación no la trae, después correr `installation:sync-correlativos`

### Roles y permisos (Spatie)

**Catálogo homologado (organigrama Huacachin):**

| Rol | Descripción |
|---|---|
| `director` | Super-rol — TODOS los permisos. Reemplaza al viejo `superusuario`. |
| `gerente` | Operativa amplia + reportes + autorizar transacciones. |
| `administrador` | Operativa + configuración completa + acceso cross-headquarter. |
| `analista-creditos` | Captura clientes/créditos, scope a sus propios clientes. Reemplaza `asesor`. |
| `area-legal` | Lectura amplia + cambio de estado para casos judiciales. |
| `cobranzas` | Recibe pagos, ve cartera por cobrar. Reemplaza `cobranza`. |
| `caja` | Apertura/cierre, ingresos/egresos, reportes de caja. |
| `contabilidad` | Lectura + edición de caja histórica. |
| `marketing` | Solo lectura de cartera para análisis comercial. |

**Permisos**: 40 totales — 27 de "acceso a página" + 13 finos de acción (eliminar, editar histórico, scope-propio, bypass-fecha, autorizar, etc.). Ver `database/seeders/PermissionCatalogSeeder.php` para el catálogo completo.

**Roles legacy eliminados**: `superusuario`, `asesor`, `cobranza`, `web`. Si la importación trae usuarios con esos nombres, el comando `installation:migrate-roles` los re-mapea automáticamente al equivalente nuevo (ya está incluido en `installation:run-all`).

**Mapping aplicado por `migrate-roles`:**

| Rol legacy | Rol nuevo |
|---|---|
| `superusuario` | `director` |
| `asesor` | `analista-creditos` |
| `cobranza` | `cobranzas` |
| `web` | (sin reasignar — usuario queda sin rol) |

**Seeders en orden:**
```bash
php artisan db:seed --class=PermissionCatalogSeeder   # 40 permisos
php artisan db:seed --class=RoleSetupSeeder           # 9 roles
php artisan db:seed --class=RolePermissionSeeder      # matriz rol×permiso
```

`DatabaseSeeder` los llama en este orden automáticamente. Todos son **idempotentes** — se pueden re-ejecutar sin daños.

**Permiso clave para usuarios nuevos**: para que un usuario aparezca en los selects de "Asesor responsable" (al crear cliente/crédito/refinanciamiento) necesita el permiso `creditos.ser-asesor-responsable`. Por defecto lo tienen los roles `analista-creditos` y `cobranzas`.

---

## 7. Troubleshooting

### `php artisan migrate` falla
- Verificar conexión BD en `.env`
- Si hay tablas existentes con conflicto: `php artisan migrate:fresh` (CUIDADO: borra todo)

### Imágenes 404
- Verificar `php artisan storage:link`
- Verificar permisos de `storage/app/public/`
- Verificar que el archivo físico exista en disco

### Búsqueda RENIEC no funciona
- Verificar `MIGO_PE_TOKEN` en `.env`
- Ver `config/services.php` → `migo.token`

### Pagos masivos con totales mal
```bash
php artisan mass-deletions:fix-amounts --dry-run  # ver qué cambiaría
php artisan mass-deletions:fix-amounts            # aplicar
```

### Sidebar no muestra módulos
- Usuario sin roles asignados → asignar desde `/users/{id}/perms`
- Permisos sin migrar → `php artisan migrate`

### AUTO_INCREMENT bajo (errores duplicate key al crear)
```bash
php artisan installation:fix-autoincrement --dry-run
php artisan installation:fix-autoincrement
```

### Health check final
```bash
php artisan installation:check
```

---

## 8. Refresh de producción desde el legacy (runbook — probado 2026-08-07 y 2026-08-09)

Ciclo completo para regenerar `laravel_prestamo` con datos frescos del legacy y
subirlo a producción. El legacy (`/var/www/prestamo`) **sigue vivo y el equipo
registra en ambos sistemas**, así que este ciclo se repite en cada corte.

> Todo el trabajo pesado se hace **en local**; producción solo se lee (dump) y
> al final se reemplaza. Duración típica: 20–30 min.

### Mapa rápido

| Qué | Dónde |
|---|---|
| Servidor | `ssh huacachin-nuevo` (root, sudo sin password) |
| App nueva | `/var/www/prestamoh` · dominio `prestamoh.huacachin.pe` |
| Legacy (vivo) | `/var/www/prestamo` · imágenes en `sistema/{cliente_captura,62a3,62a}` |
| BD local nueva | `laravel_prestamo` |
| BD local legacy | `huacachi_prestamo` ← **con las dos `c`** (hay una `huacahi_prestamo` con typo, vacía y sin uso) |
| MySQL local | `/Users/Shared/DBngin/mysql/8.0.33/bin`, `root` sin password |
| Backups del servidor | `/var/backups/mysql` (diario 02:15, retención 30 días) |
| Auth MySQL en el servidor | por socket como root: **sin contraseña en el comando** |

### 8.1 Dump del legacy en producción y descarga

```bash
# En el servidor (~2 min, 442 MB comprimidos)
ssh huacachin-nuevo 'mysqldump --single-transaction --quick --routines --triggers \
  huacachi_prestamo 2>/dev/null | gzip -1 > /root/huacachi_prestamo_$(date +%F).sql.gz'

# Descargar y verificar integridad
scp huacachin-nuevo:/root/huacachi_prestamo_*.sql.gz ~/Desktop/
gzip -t ~/Desktop/huacachi_prestamo_*.sql.gz && echo OK
```

### 8.2 Restaurar el legacy en local

Recrear la base primero: si se importa encima de una existente quedan tablas
viejas que el dump no toca.

```bash
export PATH="/Users/Shared/DBngin/mysql/8.0.33/bin:$PATH"
mysql -uroot -h127.0.0.1 -e "DROP DATABASE IF EXISTS huacachi_prestamo;
  CREATE DATABASE huacachi_prestamo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c ~/Desktop/huacachi_prestamo_*.sql.gz | mysql -uroot -h127.0.0.1 huacachi_prestamo

# Sanity: ~39 tablas, y la fecha del último pago debe ser de ayer/hoy
mysql -uroot -h127.0.0.1 huacachi_prestamo -e \
  "SELECT COUNT(*) tablas FROM information_schema.tables WHERE table_schema='huacachi_prestamo';
   SELECT COUNT(*) pagos, MAX(fechaentrada) ultimo FROM huaca_ingreso;"
```

### 8.3 Reconstruir `laravel_prestamo` en local

**Los cinco pasos son obligatorios y en este orden.** `legacy:migrate` por sí
solo deja la base a medias.

```bash
cd ~/projects/prestamoh

# 1) Esquema + roles/permisos. El --seed NO es opcional: sin él no existen los
#    roles y `installation:run-all` falla en migrate-roles.
php artisan migrate:fresh --force --seed

# 2) Datos del legacy (varios minutos, ~500k pagos)
php artisan legacy:migrate

# 3) SANEAMIENTO — obligatorio. Incluye mass-deletions:fix-amounts (el legacy
#    guarda amount = cobrado + mora reservada; aquí se recalcula = SUM(details))
#    y fix-autoincrement (sin él, el primer INSERT nuevo choca por duplicate key).
php artisan installation:run-all --force

# 4) Registros de adjuntos (los ARCHIVOS van aparte, ver 8.7)
php artisan legacy:migrate --step=attachments --fresh

# 5) WebSystem queda sin rol tras migrate-roles → desactivar
mysql -uroot -h127.0.0.1 laravel_prestamo \
  -e "UPDATE users SET status='inactive' WHERE username='admin' AND name LIKE '%WebSystem%';"

# Verificación final: DEBE decir "✓ Sistema OK — sin problemas detectados."
php artisan installation:check
```

### 8.4 Validar antes de subir nada

```bash
# a) Conciliación día a día contra el legacy (lo más importante)
php artisan reports:comparar-legacy --desde=2026-08-03 --hasta=2026-08-08
# → "✓ TODO CUADRA — sin diferencias entre legacy y el sistema nuevo."

# b) Conteos legacy ↔ migrado
#    huaca_persona→clients, huaca_cab_cuentacorriente→credits,
#    huaca_cab_masivo→mass_deletions, huaca_diasmora→dias_mora,
#    huaca_moraacum→mora_acumulada, huaca_caja→cash_openings
```

**Diferencias normales** (no son error): ~17 filas de ~1.4 M que la migración
salta a propósito — cuotas y detalles de masivo cuyo padre no existe, y 1
ingreso inválido. `credit_installments` Δ2 y `mass_deletion_details` Δ15 es lo
esperado. `huaca_ingreso` se reparte en `payments` (modo=CREDITO) + `incomes`
(Fijos/Otros); los `caja=3` son espejos, no filas nuevas.

### 8.5 Exportar el resultado

```bash
mysqldump -uroot -h127.0.0.1 --single-transaction --quick --routines --triggers \
  laravel_prestamo | gzip -1 > ~/Desktop/laravel_prestamo_$(date +%F).sql.gz
gzip -t ~/Desktop/laravel_prestamo_*.sql.gz && echo OK
```

### 8.6 Subir a producción

> ⚠️ **Dos comprobaciones antes de tocar nada.** Reemplazar la BD es
> destructivo: se pierde todo lo que producción tenga y la copia local no.

**(a) ¿Producción tiene operación posterior al corte del legacy?**

```bash
ssh huacachin-nuevo 'cd /var/www/prestamoh && php artisan tinker --execute="
echo DB::table(\"payments\")->count().\" | max fecha: \".DB::table(\"payments\")->max(\"fecha\").PHP_EOL;
echo \"payments >= HOY: \".DB::table(\"payments\")->where(\"fecha\",\">=\",\"AAAA-MM-DD\")->count().PHP_EOL;"'
```
La copia local debe ser **superset** de producción. Si prod tiene pagos que la
copia no trae, **parar**: habría que reconciliar primero.

**(b) ¿Qué tablas NO vienen del legacy y prod tiene más completas?**

Éstas se pierden si no se preservan. Al 2026-08-09 son:

| Tabla | Preservar | Por qué |
|---|---|---|
| `activity_log` | **SÍ** | Auditoría (`Audit::log`) — historia real, no reproducible |
| `cache_morosidad_diaria` | **SÍ** | Snapshots diarios de morosidad |
| `mora_overrides` | **SÍ** si tiene filas | Bitácora de ajustes de mora |
| `sessions`, `cache` | No | Desechables (se vuelven a loguear) |

Detectarlas automáticamente: comparar `COUNT(*)` de cada tabla local vs prod y
listar aquellas donde prod > local.

```bash
ssh huacachin-nuevo 'set -e
# 1) Backup completo
F=/var/backups/mysql/laravel_prestamo_PRE-REPLACE_$(date +%F_%H%M).sql.gz
mysqldump --single-transaction --quick --routines --triggers laravel_prestamo | gzip -1 > "$F"
gzip -t "$F" && ls -lh "$F"
# 2) Extraer las tablas a preservar
mysqldump --single-transaction --quick laravel_prestamo \
  activity_log cache_morosidad_diaria mora_overrides | gzip -1 > /root/preservar_$(date +%F).sql.gz'

# 3) Subir el dump nuevo
scp ~/Desktop/laravel_prestamo_*.sql.gz huacachin-nuevo:/root/

# 4) Reemplazo
ssh huacachin-nuevo 'set -e
cd /var/www/prestamoh
php artisan down --render="errors::503"
mysql -e "DROP DATABASE laravel_prestamo;
  CREATE DATABASE laravel_prestamo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c /root/laravel_prestamo_*.sql.gz | mysql laravel_prestamo
gunzip -c /root/preservar_*.sql.gz   | mysql laravel_prestamo   # restaura auditoría
php artisan migrate --force            # "Nothing to migrate" esperado
php artisan permission:cache-reset
php artisan optimize:clear && php artisan optimize
php artisan installation:check         # "✓ Sistema OK"
php artisan up'

# 5) Comprobar que responde
ssh huacachin-nuevo 'curl -s -o /dev/null -w "%{http_code}\n" \
  --resolve prestamoh.huacachin.pe:443:127.0.0.1 https://prestamoh.huacachin.pe/login'   # 200
```

### 8.7 Migrar las imágenes del legacy

Los pasos anteriores migran los **registros** de adjuntos; los **archivos**
físicos van aparte. Se hace **directamente en el servidor**, porque el legacy
vive en la misma máquina. El comando solo copia (nunca borra) y salta lo que ya
existe, así que es seguro repetirlo.

```bash
ssh huacachin-nuevo 'cd /var/www/prestamoh
php artisan legacy:copy-attachment-files \
  --clients=/var/www/prestamo/sistema/cliente_captura \
  --incomes=/var/www/prestamo/sistema/62a3 \
  --expenses=/var/www/prestamo/sistema/62a --dry-run    # revisar primero

php artisan legacy:copy-attachment-files \
  --clients=/var/www/prestamo/sistema/cliente_captura \
  --incomes=/var/www/prestamo/sistema/62a3 \
  --expenses=/var/www/prestamo/sistema/62a

chown -R www-data:www-data storage/app/public'          # el comando corre como root
```

Los thumbnails salen de la subcarpeta `pe/` de cada origen. Unos ~33 registros
apuntan a archivos que ya no existen en el legacy (originales perdidos hace
tiempo): se reportan como "sin archivo en el legacy" y la galería cae elegante.
Los nombres con espacios (WhatsApp) sirven bien: el navegador los url-encodea.

### 8.8 Limpieza

```bash
ssh huacachin-nuevo 'rm -f /root/huacachi_prestamo_*.sql.gz /root/laravel_prestamo_*.sql.gz /root/preservar_*.sql.gz'
```
Los backups oficiales quedan en `/var/backups/mysql` (incluido el `PRE-REPLACE`,
que es el rollback directo).

### 8.9 Avisos al equipo tras el refresh

- **Sesiones invalidadas**: todos vuelven a iniciar sesión.
- **Contraseñas**: quedan las del legacy (columna `obs3`). Si alguien la había
  cambiado solo en el sistema nuevo, vuelve a la del legacy.
- **WebSystem** (`admin`) queda desactivado, por diseño.

### 8.10 Errores conocidos (ya pisados)

| Síntoma | Causa | Solución |
|---|---|---|
| `installation:run-all` falla en migrate-roles: "Faltan roles destino" | Se corrió `migrate:fresh` **sin** `--seed` | `db:seed --class=PermissionCatalogSeeder\|RoleSetupSeeder\|RolePermissionSeeder --force`. **No** repetir `migrate:fresh` (borraría los datos) |
| Health check: "1 usuarios activos sin rol" | WebSystem (`admin`) | Desactivarlo (paso 8.3.5) |
| `legacy:migrate` no encuentra datos | Se importó en `huacahi_prestamo` (typo) en vez de `huacachi_prestamo` | Revisar `DB_LEGACY_DATABASE` del `.env` |
| Se perdió la auditoría tras subir a prod | No se preservó `activity_log` | Paso 8.6(b) |
| `cash_openings` no cuadra con `huaca_apertura` | `huaca_apertura` es de km/placa (herencia TaxiVan) | La fuente real es `huaca_caja` |
| `composer install` falla en el servidor | `composer.lock` resuelve `symfony/console` v8 | PHP debe ser **8.4** |

---

## Resumen express

**Instalación nueva**: 8 pasos en sección 2.
**Re-importación**: limpiar (3.1) → importar (3.2) → `php artisan installation:run-all` → health check (3.4).
**Refresh de producción desde el legacy**: dump legacy (8.1) → restaurar local (8.2) → reconstruir con los 5 pasos (8.3) → validar (8.4) → exportar (8.5) → subir preservando `activity_log` (8.6) → imágenes (8.7) → limpiar (8.8).

**Comando único después de cualquier importación masiva**:

```bash
php artisan installation:run-all
```
