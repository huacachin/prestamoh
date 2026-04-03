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

## Legacy Reference
Legacy PHP code in `/Users/antonyalfredoculquicarranza/projects/prestamo/`
- `sistema/` — Backend PHP files
- `websystem/` — Web frontend
- `huacachi_prestamo.sql` — Database backup
- Tables prefixed with `huaca_`
