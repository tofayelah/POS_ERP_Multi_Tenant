-- database/sql/roles.sql
-- Idempotent creation of database roles and privileges for KC Retail ERP

DO $$
BEGIN
    -- 1. Schema Owner / Migrator (bypasses RLS, used exclusively by migrations)
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'erp_owner') THEN
        CREATE ROLE erp_owner WITH LOGIN SUPERUSER PASSWORD 'secret';
    END IF;

    -- 2. Application Runtime (enforced RLS, no bypass)
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'app_rw') THEN
        CREATE ROLE app_rw WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS PASSWORD 'secret';
    ELSE
        ALTER ROLE app_rw WITH NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
    END IF;

    -- 3. Reporting Replica (enforced RLS, read-only)
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'reporting_ro') THEN
        CREATE ROLE reporting_ro WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS PASSWORD 'secret';
    ELSE
        ALTER ROLE reporting_ro WITH NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
    END IF;

    -- 4. Platform & Telemetry (cross-tenant metrics & rollups, no direct ledger access)
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'platform_ro') THEN
        CREATE ROLE platform_ro WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS PASSWORD 'secret';
    ELSE
        ALTER ROLE platform_ro WITH NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
    END IF;
END $$;

-- Schema usage
GRANT USAGE ON SCHEMA public TO app_rw, reporting_ro, platform_ro;

-- Permissions on existing objects
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_rw;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_rw;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO reporting_ro;

-- Default privileges for future tables created by erp_owner
ALTER DEFAULT PRIVILEGES FOR ROLE erp_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_rw;

ALTER DEFAULT PRIVILEGES FOR ROLE erp_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO app_rw;

ALTER DEFAULT PRIVILEGES FOR ROLE erp_owner IN SCHEMA public
    GRANT SELECT ON TABLES TO reporting_ro;
