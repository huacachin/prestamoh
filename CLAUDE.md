# CLAUDE.md

## Project Overview

Sistema de Préstamos / Microfinanzas para Huacachin. Gestión de créditos, cuotas, pagos, mora, caja y reportes. Arquitectura basada en TaxiVan (ver documentacion-tecnica.md de referencia).

## Development Commands

```bash
composer run dev          # Full dev stack
php artisan serve         # PHP server :8000
npm run dev               # Vite dev server
npm run build             # Production build
php artisan migrate       # Database
php artisan db:seed       # Seeders
php artisan test          # Tests
./vendor/bin/pint         # Code formatting
```

## Architecture

### Stack
- **Backend**: Laravel 11, Livewire 3, Spatie Laravel Permission (RBAC)
- **Frontend**: Tailwind CSS 3, Vite 5, jQuery UI Datepicker (Spanish locale), SweetAlert
- **Exports**: Maatwebsite Excel 3.1

### Request Flow
Routes → Controllers (thin) → Livewire components → Blade views

### Livewire Pattern
- Use `public function rules()` for validation
- Communicate via `$this->dispatch('eventName', [...])` and `#[On('eventName')]`
- Alert: `$this->dispatch('successAlert', ['message' => '...'])`

### RBAC Roles
| Role | Level | Description |
|------|-------|-------------|
| SuperUsuario | 6 | Full access, cannot be deleted |
| Administrador | 5 | Manages users, reports, backups |
| Director | 4 | Supervises operations, authorizes credits |
| Asesor | 3 | Captures clients, creates credits |
| Cobranza | 2 | Registers payments, collects |
| Web | 1 | Web read-only access |

### Key Domain Models
| Model | Purpose |
|-------|---------|
| `Client` | Loan applicants with personal/contact/location data |
| `Credit` | Loan records with amount, term, interest rate, status |
| `CreditInstallment` | Payment schedule (cuotas) per credit |
| `Payment` | Payment records (capital, interest, late fees) |
| `LateFee` | Late fee tracking per credit |
| `Income` | Cash income operations |
| `Expense` | Cash expense operations |
| `CashOpening` | Daily cash opening/closing |
| `Headquarter` | Branch offices |
| `Concept` | Predefined categories |
| `ExchangeRate` | Currency exchange rates |

### Credit Types (tipoplani)
- 1 = Semanal (weekly)
- 3 = Mensual (monthly)
- 4 = Diario (daily)

### Credit Status (situacion)
- Activo, Cancelado, Refinanciado, Eliminado

### Payment Types
- CAPITAL, INTERES, MORA

### Asset Pipeline
Vite with entry points:
1. `resources/css/app.css` (Tailwind)
2. `resources/js/app.js`
3. `public/assets/scss/style.scss`

## Locale & Timezone
- Locale: `es`
- Timezone: `America/Lima`

## Módulo Área Legal

Módulo integrado (rama `feat/area-legal`): garantías mobiliarias SIGM con
generación de contratos PDF, notaría, expedientes judiciales, papeletas, caja
legal (`incomes`/`expenses` con `caja=4`, aislada de la operativa) y campana de
alertas. Rol `area-legal` con permisos `legal.*`.

**Antes de tocar migraciones/poblado del módulo o re-migrar sus datos, leer
`docs/AREA-LEGAL.md`** — documenta qué tablas escribe cada importador, las
garantías de aislamiento del sistema de préstamos (nunca se escribe en
clients/credits/installments/payments; reglas de `caja=4`), el comando
orquestador `legal:poblar {carpeta} --dry-run`, las claves de idempotencia,
los números de referencia de la corrida validada y el runbook completo.

## Auditoría

Dos vías, ambas en `activity_log` con `log_name = 'auditoria'` (spatie/activitylog v5):
- **Automática por modelo**: trait `App\Support\Auditable` (constantes `AUDIT_MODULO`,
  `AUDIT_EXCLUIR`, `AUDIT_ENMASCARAR`, método `auditNombre()`). Registra created/updated/deleted
  con `attribute_changes` (fila completa o solo lo que cambió, con valor anterior y nuevo).
  No cubre escrituras por `DB::table` ni updates masivos.
- **Manual**: `Audit::log('Verbo …', $modelo, $props)` para acciones de negocio (cobros, anulaciones…).
  El verbo inicial clasifica la acción en el visor.
- A todo registro `App\Support\Auditoria\GuardarActividad` le añade `properties.contexto`
  (IP, navegador, ruta, usuario con rol), rellena las columnas propias con la estructura de
  newtaxivan (`user_name`, `user_role`, `module`, `old_data`, `new_data`, `changed_fields`,
  `ip_address`, `user_agent`; modelo `App\Models\ActivityLog`) y nunca tumba la operación si
  falla el insert. Equivalencias: user_id = causer_id, action = event, record_id = subject_id.
- Cuando un `Audit::log` de negocio acompaña a un guardado del mismo modelo, envolver el
  guardado con `$modelo->sinAuditoriaAutomatica(fn () => ...)` para no duplicar la fila.
- Etiquetas del visor en `config/auditoria.php`. Visor: `/audit` (solo director).

## Legacy Reference
Legacy PHP code in `/Users/antony/projects/_legacy-prestamo/`
- `sistema/` — Backend PHP files
- `websystem/` — Web frontend
- `bd/huacachi_prestamo_dump_2026-06-19.sql` — Database backup (~2.1 GB, fuente de verdad actual)
- `bd/huacachi_prestamo_legacy_2026-05-30.sql` — Database backup (respaldo, venía con el código)
- Tables prefixed with `huaca_`

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
