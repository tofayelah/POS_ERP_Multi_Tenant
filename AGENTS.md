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
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

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
