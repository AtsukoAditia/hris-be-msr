# Database Documentation — HRIS-MSR

## Overview

HRIS-MSR uses **PostgreSQL 16** as its primary relational database. MySQL was removed in July 2026.

## Connection Details

| Parameter         | Value                |
|-------------------|----------------------|
| Engine            | PostgreSQL 16 (Alpine) |
| Container         | `hris-postgres`      |
| Host (internal)   | `postgres`           |
| Port              | 5432                 |
| Database          | `hris`               |
| User              | `hris`               |
| Password          | `hris_secret_2026`   |
| Volume            | `postgres_data`      |

The Laravel backend (`hris-backend`) connects via `DB_CONNECTION=pgsql`, `DB_HOST=postgres`.

## Migration & Seeding

```bash
# Run migrations
docker exec hris-backend php /var/www/artisan migrate

# Check migration status
docker exec hris-backend php /var/www/artisan migrate:status

# Seed the database
docker exec hris-backend php /var/www/artisan db:seed

# Fresh migration (drops all tables, re-runs)
docker exec hris-backend php /var/www/artisan migrate:fresh --seed
```

## Backup & Restore

### Backup

```bash
# Dump entire database
docker exec hris-postgres pg_dump -U hris hris > backup_$(date +%Y%m%d).sql

# Compressed dump
docker exec hris-postgres pg_dump -U hris -Fc hris > backup_$(date +%Y%m%d).dump
```

### Restore

```bash
# From plain SQL
cat backup.sql | docker exec -i hris-postgres psql -U hris -d hris

# From custom-format dump
cat backup.dump | docker exec -i hris-postgres pg_restore -U hris -d hris
```

### Automated Backups

Set up a cron job on the host:

```bash
0 2 * * * docker exec hris-postgres pg_dump -U hris -Fc hris > /backups/hris_$(date +\%Y\%m\%d).dump
```

## Switching from MySQL (Historical)

The switch from MySQL to PostgreSQL was completed in July 2026:
- MySQL container (`hris-mysql`) removed
- MySQL volume (`hris-msr_mysql_data`) deleted
- `docker-compose.yml` updated to PostgreSQL 16
- Laravel `DB_CONNECTION` changed to `pgsql`
- PHP `pdo_pgsql` extension enabled in backend Dockerfile
- All migrations re-run against PostgreSQL
