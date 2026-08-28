# Módulo Área Legal — arquitectura, poblado e importadores

Documento de referencia para operar y **re-migrar** el módulo legal.
Última actualización: 2026-08-25 (rama `feat/area-legal`, commits `bd8d52d` + `a5054bb`).

---

## Tabla de contenidos

- [1. Qué es y dónde vive](#1-qué-es-y-dónde-vive)
- [2. Tablas del módulo y qué toca cada importador](#2-tablas-del-módulo-y-qué-toca-cada-importador)
- [3. Garantías de aislamiento del sistema de préstamos](#3-garantías-de-aislamiento-del-sistema-de-préstamos)
- [4. El poblador: legal:poblar](#4-el-poblador-legalpoblar)
- [5. Detalle por importador (reglas, claves de idempotencia, limitaciones)](#5-detalle-por-importador)
- [6. Números de referencia de la corrida validada](#6-números-de-referencia-de-la-corrida-validada)
- [7. Runbook: re-migrar desde cero](#7-runbook-re-migrar-desde-cero)
- [8. Refresh de producción y preservación](#8-refresh-de-producción-y-preservación)
- [9. Pendientes conocidos tras poblar](#9-pendientes-conocidos-tras-poblar)

---

## 1. Qué es y dónde vive

Módulo "Área Legal" integrado a prestamoh (mismo app, mismo login, servido también
en `legal.test` vía Herd link). Submódulos: **Garantías SIGM** (con generación de
contratos en PDF), **Vehículos**, **Notaría**, **Expedientes Judiciales**,
**Papeletas** y **Caja Legal**, más la campana `LegalBell` del navbar (5 fuentes
de alerta self-heal: renovaciones SIGM, notaría varada, plazos judiciales,
recursos de papeletas y vencimientos documentarios de flota).

- Rol `area-legal` asignable, con 7 permisos `legal.*` (catálogo en
  `PermissionCatalogSeeder`; el rol `director` ve todo).
- Los datos históricos provienen de los Excel de la carpeta
  **"Proyecto. Sistema Area Legal"** (en la máquina de Antony:
  `/Users/antony/projects/legal/Proyecto. Sistema Area Legal`).
- Código: `app/Livewire/Legal/**`, `app/Models/` (12 modelos nuevos),
  `app/Services/Legal/**` (motor de contratos), `app/Support/Legal/**`
  (Genero, Ordinales, FechaEnLetras, BancosVoucher, LegalSettings),
  `app/Support/Correlativo.php`, `app/Console/Commands/Legal*.php`,
  `resources/views/legal/**` (incl. `pdf/` con los partials del contrato).

## 2. Tablas del módulo y qué toca cada importador

Orden de ejecución = orden de dependencias (vehículos primero).

| # | Comando | **Escribe en** | Lee (matching, NUNCA escribe) |
|---|---|---|---|
| 1 | `legal:importar-flota` | `vehiculos` | — |
| 2 | `legal:importar-garantias` | `garantias`, `sigm_avisos`, `vehiculos` (si falta la placa) | `clients` (por DNI), `credits` |
| 3 | `legal:importar-notaria` | `tramites_notariales` | `clients` (nombre), `garantias` (placa), `users` |
| 4 | `legal:importar-expedientes` | `expedientes_judiciales`, `actuaciones_judiciales`, `plazos_judiciales` | `clients` (exp_interno = `clients.expediente`, fallback nombre), `users` |
| 5 | `legal:importar-papeletas` | `papeletas`, `papeleta_recursos`, `vehiculos` (mínimos con nota) | — |
| 6 | `legal:importar-caja` | `incomes` y `expenses` — **solo filas `caja=4`** | `users` ("Rosa T.") |

Tablas nuevas adicionales que NO puebla ningún importador (se llenan por uso):
`contratos` (emisiones PDF), `garantia_vehiculo` (la crea el importador de
garantías vía attach), `legal_adjuntos`, `legal_settings` (la siembra
`LegalSettingsSeeder` con el acreedor, apoderada, representantes, cuentas).

**Columnas agregadas a tablas preexistentes** (aditivas, nullable):
`clients.estado_civil/ocupacion/nacionalidad` (las exige el contrato; ninguna
pantalla previa las usa) y `vehiculos.soat_vence/revision_tecnica_vence/
habilitacion_atu_vence`.

## 3. Garantías de aislamiento del sistema de préstamos

**Poblar el módulo legal NO altera el sistema de préstamos.** Punto por punto:

1. **Jamás se escribe** en `clients`, `credits`, `credit_installments` ni
   `payments`. El importador de garantías **rechaza crear clientes**: deudor del
   Excel sin match por DNI → garantía omitida con nota en el resumen.
2. **Caja compartida pero aislada**: los asientos legales llevan `caja=4`
   SIEMPRE explícito (¡el default de la columna es 1!). Auditoría completa de
   los consumidores de `caja` aplicada en el commit `bd8d52d`:
   - Pantallas de caja operativa (`Cash/Incomes`, `Cash/Expenses`, exports)
     filtran `caja=1` desde antes — no ven caja=4.
   - `Reports/Cash` quedó con `whereIn('caja',[1,3])` (antes sumaba TODO —
     bug preexistente que además duplica los espejos caja=3; se conservó el
     comportamiento 1+3 para no alterar un reporte que el negocio concilia).
   - La huella de caché de `Reports/CashStatistics` filtra `[1,3]` (un asiento
     legal no invalida su caché mensual).
   - `EditIncome`/`EditExpense`/`IncomeGallery`/`ExpenseGallery` abortan **403**
     ante una fila `caja=4`: los asientos legales solo se gestionan desde su
     documento de origen (aviso SIGM / trámite notarial / tablero legal).
   - **PROHIBIDO** usar `mass_deletion_id` o `parent_id` en asientos legales
     (la reversa de cobros por depósito borra expenses por `mass_deletion_id`
     sin filtrar caja; `parent_id` es del espejo caja=3).
3. `CashOpening` (apertura/arqueo), `CajaDailyService`, `CashGeneral1/2/3` y el
   dashboard leen `payments`/`credits` o filtran caja 1/3 — sin cambios.
4. Correlativos: el legal usa su propio tipo `'Contrato'` en `correlativos`
   (vía `App\Support\Correlativo::siguiente()`, con `lockForUpdate` — a
   diferencia del patrón legacy de Cliente/Credito, que no se tocó).
5. **Tests que lo prueban** (`tests/Feature/Legal/CajaLegalTest.php`): la
   pantalla operativa no muestra caja=4, `Reports\Cash` da el mismo balance con
   y sin asientos legales, y editar un caja=4 desde Caja responde 403. La suite
   completa (170 tests, incluidos los ~115 previos de préstamos) queda en verde
   tras poblar.

## 4. El poblador: legal:poblar

```bash
# SIEMPRE primero en seco: nada se persiste, ves el resumen completo
php artisan legal:poblar "/ruta/a/Proyecto. Sistema Area Legal" --dry-run

# Corrida real
php artisan legal:poblar "/ruta/a/Proyecto. Sistema Area Legal"

# Un solo paso (repetible): flota|garantias|notaria|expedientes|papeletas|caja
php artisan legal:poblar "/ruta" --solo=caja --dry-run
```

- Espera esta estructura EXACTA dentro de la carpeta (nombres de archivo del área):
  - `1. Constitucion de Garantia Mobiliaria SIGM/B. 2026. Registro de garantías constituidas - SIGM.xlsx`
  - `1. Constitucion de Garantia Mobiliaria SIGM/D. Notaria Hinojosa. Registro de documentos pendientes.xlsx`
  - `2. Expedientes Judiciales/A. Registro de seguimiento. Expedientes Judiciales.xlsx`
  - `3. Tramites administrativos. Papeletas/1. Relacion de Vehiculos G-IH-CH (13-3-24).xlsx` (flota Y papeletas)
  - `Cuadre de caja Area Legal. Ingreso y egreso.xlsx`
- Archivo faltante → warn + salto (el resto sigue); exit 1 si algún paso falla.
- **Todos los importadores son idempotentes**: re-ejecutar no duplica (ver
  claves por comando abajo). PERO no actualizan lo ya importado: si el Excel
  cambió una fila ya migrada, el registro existente NO se pisa (se salta como
  duplicado) — las correcciones post-migración se hacen en el sistema.

## 5. Detalle por importador

### legal:importar-flota
- Hojas `Vehic.` (activos), `V.Veh.` (vendidos), `Veh. Cresencio` (tercero).
- Clave: **placa** (mayúsculas). Si el vehículo ya existe (p. ej. creado por
  garantías) solo COMPLETA campos vacíos (año, SOAT, revisión, ATU); nunca pisa
  `client_id` ni valores existentes.
- Fechas absurdas (vigencia ATU `1932-12-31` real del Excel) o texto `VENCIDO`
  → NULL con nota. Detecta la fila D1W950 corrida una columna y la repara.

### legal:importar-garantias (registro B)
- 7 hojas por tasa (`G.M 3%`…`G.M 10%`); filas de renovación (sin deudor) se
  agrupan a la garantía anterior; co-deudores por fila adicional.
- Matching: cliente por DNI exacto (DNI de 7 dígitos se busca con cero a la
  izquierda pero marca revisión); crédito = último Activo del cliente.
- Clave de idempotencia: **`nro_formulario` del aviso de constitución** (unique
  en BD junto con `folio`). El mismo aviso duplicado en dos hojas del Excel se
  deduplica solo.
- Importa con `vigencia_hasta = NULL` (el Excel dice "Indeterminado") → esas
  garantías NO alertan renovación hasta registrar su vigencia real.
- `gps=false` y `monto_gravamen=NULL` (el Excel no los trae): completar por
  garantía con el botón **Editar** del detalle (sugiere el total del cronograma).

### legal:importar-notaria (registro D)
- 3 hojas: SIGM, G.Hipotecaria, Otros. Estado derivado de las fechas y los
  textos `Pendiente`/`No entregado`/`No firmo`. `estado_desde` alimenta la
  alerta de varados (>15 días).
- Filas sin ninguna fecha se omiten (estado_desde es NOT NULL, no se inventan).
- Idempotencia: tipo + cliente/descripción + estado_desde.

### legal:importar-expedientes (registro A judicial)
- 5 hojas → vía (captura/inscripción/planilla) o estado forzado
  (condonado/cancelado). Cada hoja puede repetir cabeceras internas.
- Normaliza el N° del PJ (espacios, pipes, `--`, 6 dígitos) y valida contra el
  formato estándar; el cautelar registrado con `-0-` se deriva a `-1-` con
  `ExpedienteJudicial::nroCautelarDesde()` (marca revisión).
- Clave: **`nro_expediente`** (unique). Resolución + escritos → actuaciones.
- **Extrae plazos del texto libre** ("VENCE 09/03/2026", "Venc. 22/01") a
  `plazos_judiciales`; los vencidos quedan visibles en la campana a propósito.

### legal:importar-papeletas (hojas Pap.* del Excel de flota)
- Clave: **`entidad` + `nro_papeleta`** (unique compuesto) — la misma acta
  digitada en hasta 6 hojas se importa UNA vez.
- Filas de Pap.1 **sin N° de papeleta se omiten** (no se inventan claves): esa
  deuda (~S/ 28k) queda fuera hasta completar los números desde los portales.
- Recursos históricos de los Excel de solicitudes SAT/ATU NO se vinculan
  (sin referencia confiable a papeleta): se registran vía UI en adelante.

### legal:importar-caja (Cuadre de caja)
- Hojas mensuales Enero–Junio (maneja el cambio de formato de mayo–junio:
  3 bloques con cabeceras propias, detectadas por contenido). `Balance ` se
  omite (consolidado derivado).
- Por fila: la **tarifa** es el ingreso real (el monto de la garantía es el
  préstamo — solo va al detalle como contexto) + hasta 3 egresos (Tasa SIGM,
  Gasto notarial, Otros). Gasto puro (sueldos, suscripciones) → solo egreso.
- `caja=4` explícito, modo `Otros`, documento `GUIA`; usuario "Rosa T." →
  match único por nombre o NULL.
- Clave de idempotencia: **(tabla, caja=4, date, total, detail)** — el detail
  es determinista (placa, cliente, SIGM, forma de pago, hoja), así re-ejecutar
  no duplica.
- Fechas fuera del mes de la hoja (los `2025-01-xx` de Enero) se importan con
  su fecha original + prefijo `[REVISAR fecha]` en el detalle y nota en el
  resumen; `13/03//2026` se repara colapsando `//`.
- El resumen imprime el cuadre contra la referencia del Balance del Excel.

## 6. Números de referencia de la corrida validada

Corrida del 25-08-2026 sobre los Excel del área (para cotejar re-migraciones —
si los Excel no cambiaron, estos números deben repetirse):

| Paso | Resultado |
|---|---|
| Flota | 68 vehículos creados + 3 completados (2 ya completos) |
| Garantías | 104 garantías, 126 avisos; 11 omitidas (clientes sin match), 10 con revisión, 89 vigentes |
| Notaría | 99 trámites (83 SIGM + 5 hipotecarias + 11 otros); 5 omitidos; 18 varados detectados |
| Expedientes | 146 principales + 78 cautelares, 238 actuaciones, 7 plazos (todos vencidos → campana); 218/224 clientes matcheados |
| Papeletas | 29 papeletas (12 duplicados deduplicados, 26 sin N° omitidas); deuda S/ 85,211.97 de los S/ 113,164.75 del Excel |
| Caja | 154 ingresos + 360 egresos (237 SIGM S/ 948 + 84 NOT S/ 3,520 + 39 OTROS S/ 26,614.10); **cuadre exacto**: ingresos S/ 46,241.60, egresos S/ 31,082.10, neto S/ 15,159.50 |

## 7. Runbook: re-migrar desde cero

```bash
# 0) Prerrequisitos: rama feat/area-legal desplegada; carpeta de Excel accesible
php artisan migrate --force            # 15 migraciones del módulo (2026_08_24_* y 2026_08_25_*)
php artisan db:seed --class=PermissionCatalogSeeder --force
php artisan db:seed --class=RoleSetupSeeder --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=LegalSettingsSeeder --force   # acreedor, apoderada, cuentas (firstOrCreate: no pisa ediciones)
php artisan storage:link               # los PDF de contratos van a storage/app/public/legal/
php artisan route:clear && php artisan view:clear   # ¡la caché de rutas vieja da 404 en las rutas legal.*!

# 1) Previsualizar TODO
php artisan legal:poblar "/ruta/a/Proyecto. Sistema Area Legal" --dry-run
#    → revisar las tablas de notas de cada paso (omitidos y requiere_revision)

# 2) Corrida real
php artisan legal:poblar "/ruta/a/Proyecto. Sistema Area Legal"

# 3) Verificación post
php artisan test                       # 170 tests deben quedar en verde
#    cotejar los contadores contra la sección 6 de este documento
#    revisar en la UI: filtro "requiere revisión" en Garantías/Papeletas/Notaría
```

Notas:
- `PermissionCatalogSeeder` BORRA todo permiso fuera del catálogo — los 7
  `legal.*` ya están en él; no re-sembrar con una versión vieja del seeder.
- `PermisosVistaSeeder` (si se corre) resetea los checkboxes de vista de TODOS
  los usuarios a la matriz de su rol — comportamiento preexistente, no del módulo.
- El orden de los pasos del poblador importa: no correr `--solo=garantias`
  antes que flota en una BD vacía (funciona, pero crea vehículos mínimos que
  flota luego solo completa).

## 8. Refresh de producción y preservación

Las tablas del módulo NO vienen del legacy: están en la lista de preservación
del runbook (`docs/INSTALACION.md` §8.6b, ya actualizada): `vehiculos`,
`garantias`, `garantia_vehiculo`, `sigm_avisos`, `contratos`, `legal_adjuntos`,
`legal_settings`, `tramites_notariales`, `expedientes_judiciales`,
`actuaciones_judiciales`, `plazos_judiciales`, `papeletas`, `papeleta_recursos`.
**Ojo con `incomes`/`expenses`**: sí vienen del legacy — un refresh que las
reemplace pierde los asientos `caja=4`; preservarlos aparte
(`WHERE caja = 4`) o re-importar la caja después del refresh
(`legal:poblar ... --solo=caja`, idempotente).

## 9. Pendientes conocidos tras poblar

1. **Vigencias SIGM**: registrar la vigencia real de los avisos vigentes (el
   Excel decía "Indeterminado") para activar las alertas de renovación.
2. **monto_gravamen / valor del vehículo / GPS**: completar por garantía con el
   botón Editar antes de emitir su contrato (el validador bloquea si faltan).
3. **26 papeletas sin N°** (~S/ 28k): completar los números desde los portales
   SAT/ATU y registrarlas con el modal.
4. **Fichas de cliente**: el contrato exige estado civil, ocupación y correo —
   el wizard de emisión los pide inline si faltan.
5. **Deuda técnica preexistente documentada**: `Reports/Cash` duplica los
   espejos caja=3 (decisión de negocio pendiente, no se alteró).
6. Test flaky ocasional preexistente en `Payments/Create` (ajeno al módulo).
