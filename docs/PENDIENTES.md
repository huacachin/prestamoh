# Tareas pendientes

## Contratos — el default falso de `ocupacion` / `estado_civil` (decisión de negocio, 2026-08-28)

**Estado:** el guard de emisión (tramo B) NO cubre este caso, y es a propósito.

`clients.ocupacion` y `clients.estado_civil` se crearon en
`2026_08_28_000001` como **NOT NULL con default** `'transportista'` y
`'soltero'`. Todos los clientes migrados del legacy quedaron declarados
como transportistas solteros sin que nadie lo afirmara: en un contrato
firmado eso no es un dato faltante, es un **dato falso indistinguible de
uno real**.

El guard de `GeneradorContrato::validar()` detecta lo AUSENTE, no lo
FALSO, así que un cliente migrado pasa el guard y emite el contrato con
esos valores. Está cubierto por
`ContratoTramoBTest::test_limite_conocido_el_default_falso_del_legacy_pasa_el_guard`,
que documenta el hueco en vez de taparlo.

**Por qué se dejó así:** el módulo de contratos se usa solo con clientes
nuevos, y el alta ya exige ambos campos de verdad. Tocar el dato
histórico (≈170k filas) se descartó explícitamente.

### Qué haría falta para cerrarlo

1. ~~`ALTER` de las dos columnas a `nullable()`~~ — **HECHO en la fusión
   con feat/area-legal** (`2026_08_28_100014`): adoptamos su diseño. No
   tocó ninguna fila; solo destraba el paso 2.
2. Un `UPDATE` que ponga `NULL` en las filas que nunca declararon el
   dato. El problema es distinguirlas: hoy un transportista soltero real
   y uno por default son idénticos. La heurística posible es
   `created_at < fecha del despliegue del tramo B`.
3. Recién entonces el guard bloquea al cliente viejo y obliga a
   completarlo desde la pestaña de editar cliente (que desde el tramo B
   ya guarda ambos campos).

---

## GPS de cliente — reestructuración pendiente (deuda técnica, 2026-08-28)

**Estado:** funcional en la pestaña GPS de editar cliente; pendiente rediseño.

Las columnas "C." (casa) y "N." (negocio) del listado de clientes se
movieron a la pestaña **GPS** de `/clients/{id}/edit`
(`App\Livewire\Clients\Gps`), con la misma mecánica de siempre: pegar
"lat, lng" o un enlace de Google Maps. El parser quedó extraído a
`App\Support\Coordenadas` (antes vivía inline en el listado).

### Qué falta al reestructurar

1. **Capturar desde el navegador**: hoy se pega texto. En el celular
   podría usarse `navigator.geolocation` (como ya hace /payments/create)
   para tomar la ubicación al estar parado en la puerta del cliente.
2. **Mapa embebido** para confirmar el punto antes de guardar, en vez de
   abrir Google Maps en otra pestaña.
3. **Más de dos ubicaciones**: hoy son dos columnas fijas en `clients`
   (latitud/longitud y latitud2/longitud2). Si aparecen más direcciones,
   conviene una tabla propia.
4. **Historial**: los cambios quedan en `activity_log` pero no se ve
   quién movió un punto ni cuándo desde la pantalla.

---

## Avales — reestructuración pendiente (deuda técnica, 2026-08-28)

**Estado:** botón OCULTO en `/clients`, funcionalidad intacta.

El botón "Aval" del listado de clientes se escondió el 28/08 a pedido de
Antony, junto con Adjuntos y Documentos (esos dos sí migraron a pestañas
dentro de editar cliente). **No se borró nada**: la ruta
`clients/{id}/aval`, el componente `App\Livewire\Clients\Aval`, la tabla
`client_avales` y todos sus datos siguen igual. Para volver a mostrarlo
basta quitar los dos `@if(false)` de
`resources/views/livewire/clients/index.blade.php` (marcados con el
comentario "OCULTO 28/08") y devolver la cabecera `Opciones` a
`colspan="2"`.

### Qué hay que decidir al reestructurar

1. **Dónde vive**: lo natural sería una cuarta/quinta pestaña en editar
   cliente, como quedaron Vehículos, Documentos y Adjuntos.
2. **Duplicación de la consulta de documento**: `Aval::consultarDni()`
   nació como copia paralela de la del alta. Hoy ya comparte el servicio
   `App\Services\Factiliza`, pero conserva reglas propias (busca primero
   en BD local, bloquea auto-aval y duplicados) que convendría unificar.
3. **Modelo de datos**: `client_avales` guarda el nombre en UN solo campo
   mientras `clients` lo tiene partido en tres. Al consultar el mismo DNI
   desde el alta queda separado y desde avales queda entero — hay que
   decidir un único formato.
4. **`withCount('avales')`** en `App\Livewire\Clients\Index` se dejó a
   propósito aunque el botón esté oculto (una subconsulta por listado),
   para que restaurar sea solo quitar los `@if(false)`. Si la
   reestructuración tarda, conviene retirarlo.

---

## Optimización de rendimiento de `/clients`

**Estado:** Pendiente de aprobación por Gerencia  
**Fecha de auditoría:** 2026-07-20  
**Alcance:** Carga inicial, interacciones Livewire, consultas SQL y recursos frontend.

### Línea base auditada

| Métrica | Resultado actual |
|---|---:|
| Respuesta autenticada caliente | 42–45 ms |
| Consultas SQL | 12 por carga, 12.5–14.6 ms |
| HTML inicial | ~920 KB crudo / ~48 KB gzip |
| Elementos DOM del componente | ~6,000 |
| Respuesta de filtro Livewire | ~866 KB JSON |
| Respuesta de paginación Livewire | ~766 KB JSON |
| Actualización del check de WhatsApp | ~887 KB JSON |
| Recursos iniciales referenciados | ~2.34 MB antes de fuentes/avatar externos |

### P0 — Mayor impacto

- [ ] Sustituir el hook global `morph.updated` del datepicker por una sola ejecución posterior al morph/commit, limitada al componente que realmente tenga campos `.dates*`.
- [ ] No cargar jQuery UI ni sus inicializadores en `/clients`, porque esta pantalla no contiene datepickers.
- [ ] Unificar la tabla desktop y las tarjetas móviles en una sola estructura responsive.
- [ ] Reducir la paginación inicial de 100 a 25–50 clientes, conservando una opción explícita para mostrar más.
- [ ] Añadir `wire:key` estable a cada cliente renderizado.
- [ ] Evitar que `notif-enviada` reconstruya toda la lista; actualizar únicamente el check del cliente y omitir el render del padre.
- [ ] Aislar el modal de coordenadas en un componente hijo para que abrirlo o guardarlo no vuelva a consultar y renderizar la lista completa.
- [ ] Crear una carga de recursos por página y excluir de `/clients` los recursos no utilizados: Animate, jQuery UI, Weather Icons, Font Awesome, Flag Icons y Prism.
- [ ] Reemplazar el favicon de 1.19 MB por un archivo optimizado de 32–64 px.
- [ ] Eliminar la solicitud de `customizer.txt` cuando el customizer no está montado y quitar el retraso artificial de un segundo al aplicar el tema.

### P1 — Backend y consultas

- [ ] Separar el query builder base de filtros de los eager loads, `withCount` y proyecciones de la página.
- [ ] Hacer que la obtención de IDs seleccione exclusivamente `clients.id`.
- [ ] Seleccionar únicamente las columnas de cliente visibles en la lista.
- [ ] Sustituir `withCount(['avales', 'attachments'])` por comprobaciones `EXISTS`.
- [ ] Eliminar el eager load de `headquarter`, que no se utiliza en la vista.
- [ ] Integrar la existencia de crédito activo y notificación del día en consultas `EXISTS` de la página.
- [ ] Evitar trasladar todos los IDs filtrados a PHP; usar subconsulta o `JOIN` directo para morosidad.
- [ ] Cachear o cargar una sola vez el selector de asesores, con invalidación cuando cambien usuarios o permisos.
- [ ] Reescribir `whereDate(created_at)` como rango `[inicio del día, inicio del día siguiente)`.
- [ ] Añadir el índice compuesto `client_notifications (client_id, created_at)`.

### P2 — Escalabilidad y mantenimiento

- [ ] Sustituir `ORDER BY CAST(expediente AS UNSIGNED)` por una columna numérica o generada e indexar `(status, expediente_num, id)`.
- [ ] Mantener `id` como segundo criterio de orden porque existen expedientes duplicados.
- [ ] Evaluar búsqueda `FULLTEXT` para nombres cuando la cantidad de clientes activos lo justifique.
- [ ] Normalizar zona y giro para búsquedas exactas o por prefijo antes de crear índices adicionales.
- [ ] Convertir los iconos usados en `/clients` a SVG o generar un subconjunto de Tabler en lugar de descargar la fuente completa.
- [ ] Autoalojar únicamente los pesos de Poppins realmente usados y generar el avatar localmente.
- [ ] Versionar los recursos estáticos y configurar caché explícita `immutable` para archivos con hash.
- [ ] Poner primero como invisible el índice redundante `credit_installments_credit_id_pagado_index`, observar una ventana representativa y eliminarlo solo si no hay regresiones.
- [ ] Actualizar `AGENTS.md` y `CLAUDE.md`: el proyecto ejecuta Laravel 13 y Livewire 4.2, no Laravel 11/Livewire 3.

### Restricciones funcionales

- Los índices actuales de morosidad sí son utilizados; no crear índices duplicados.
- Un cliente puede tener varios créditos activos. El semáforo debe conservar el máximo de cuotas vencidas de un crédito individual, no sumar créditos distintos.
- Existen 42 expedientes duplicados y 4 documentos duplicados; no crear índices `UNIQUE` sin saneamiento previo.
- La optimización no debe cambiar filtros, colores de morosidad, permisos, acciones ni exportaciones.

### Criterios de aceptación

- [ ] HTML inicial menor a 180 KB crudos.
- [ ] Menos de 2,000 elementos DOM en `/clients`.
- [ ] Respuestas Livewire menores a 180 KB crudos.
- [ ] Transferencia inicial comprimida menor a 600 KB, sin dependencias externas bloqueantes.
- [ ] Render autenticado caliente menor a 35 ms en el entorno de referencia.
- [ ] Interacciones Livewire p95 menores a 100 ms.
- [ ] Sin N+1 y con planes `EXPLAIN ANALYZE` documentados para los queries principales.
- [ ] Pruebas funcionales de filtros, paginación, morosidad, WhatsApp, coordenadas y permisos.
- [ ] Medición comparativa antes/después con navegador y perfil de red equivalente.

