# Retail ERP: rules for AI agents

## Read first
- Design: docs/design/ERP_SYSTEM_DESIGN.md (v1.5.0)
- Latest review: docs/reviews/ERP_DESIGN_REVIEW_v1.5.0.md
- Step map: docs/build/steps.md · Decisions: docs/adr/
- How to run anything: .agents/rules/dev-server.md
- Where the review and the design disagree, follow the review and say so.

## Stack
Laravel 13, PHP 8.4, Filament 5 (Livewire 4), PostgreSQL 17, Redis 7, Horizon,
Pest, Larastan level 8, Pint. POS: Alpine.js + Dexie PWA in resources/pos.
Bridge: Go in bridge/. Modules live in app/Core and app/Modules/{Name};
one module uses another only through app/Modules/{Name}/Contracts.

## Commands (all run on the dev server, in the project folder)
- Services: docker compose up -d
- Tests: php artisan test (one file: php artisan test tests/Database/XTest.php)
- Format: vendor/bin/pint --test · Static analysis: vendor/bin/phpstan analyse
- RLS check: php artisan db:check-rls
- POS: npm run build · npx vitest run · npx playwright test
- Bridge: cd bridge && go vet ./... && go test ./...

## Non-negotiable rules
1. Every tenant table is created with the RLS migration helper (tenant_id,
   UNIQUE (tenant_id, id), forced RLS, fail-closed policy).
2. Foreign keys between tenant tables are composite: (tenant_id, …), and
   (tenant_id, company_id, id) where the design requires company consistency.
3. The application connects as app_rw. Migrations run on pgsql_owner.
   Application code never uses the owner connection.
4. Tenant context is set with set_config('app.current_tenant_id', id, false)
   per request, or with true inside a transaction. Never SET or SET LOCAL
   with a bind parameter.
5. Posted journals, issued or cancelled invoices and stock movements are never
   updated or deleted. Corrections are reversals, credit notes or new documents.
6. Money uses brick/math and numeric columns, is a string in JSON, and rounds
   half-up to 2 places. No floats anywhere in money code.
7. GL posting happens in the same database transaction as the business
   document, never in a queued job.
8. Database tests run on PostgreSQL 17 as app_rw. Never use SQLite, and never
   weaken a constraint, trigger, policy or test to make something pass.

## How to work
- Plan first: write docs/build/step-NN-plan.md and stop for approval.
- Test first: write the failing test, run it on the server and show the failure,
  then implement.
- Small commits with conventional messages that name the review IDs closed.
- After each commit, run Pint, PHPStan and the affected tests on the server;
  paste the output.
- Never read or print .env files, SSH keys or other secrets. Never push to main
  or force-push.
- If the design is ambiguous, stop and ask. Record decisions in docs/adr/.

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
