# Guía de Instalación · Sistema Préstamos Huacachin

Documento de referencia para **instalar desde cero** y **re-importar datos legacy**.
Última actualización: 2026-05-09.

---

## Tabla de contenidos

- [1. Requisitos](#1-requisitos)
- [2. Instalación inicial (primera vez)](#2-instalación-inicial-primera-vez)
- [3. Re-importación de datos legacy](#3-re-importación-de-datos-legacy)
- [4. Comandos artisan disponibles](#4-comandos-artisan-disponibles)
- [5. Estructura de tablas críticas](#5-estructura-de-tablas-críticas)
- [6. Notas para el script de importación](#6-notas-para-el-script-de-importación)
- [7. Troubleshooting](#7-troubleshooting)

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
| `huaca_cliente_ima` | `client_attachments` (+ copia archivos físicos a `storage/app/public/clients/{id}/`) |
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

## Resumen express

**Instalación nueva**: 8 pasos en sección 2.
**Re-importación**: limpiar (3.1) → importar (3.2) → `php artisan installation:run-all` → health check (3.4).

**Comando único después de cualquier importación masiva**:

```bash
php artisan installation:run-all
```
