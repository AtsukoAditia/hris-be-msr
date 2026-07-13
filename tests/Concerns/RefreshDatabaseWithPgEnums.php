<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends RefreshDatabase to drop PostgreSQL enum types before
 * running migrations. PostgreSQL's DROP TABLE CASCADE does NOT
 * drop the underlying enum types, causing "duplicate key value
 * violates unique constraint pg_type_typname_nsp_index" errors
 * when migrations try to recreate tables with enum columns.
 */
trait RefreshDatabaseWithPgEnums
{
    use RefreshDatabase;

    protected function refreshTestDatabase(): void
    {
        if ($this->usingPostgres()) {
            $this->dropPostgresEnumTypes();
        }

        parent::refreshTestDatabase();
    }

    /**
     * Drop all user-defined enum types in the public schema.
     * This prevents "type already exists" errors when recreating
     * tables that use enum columns.
     */
    private function dropPostgresEnumTypes(): void
    {
        try {
            $types = DB::select("
                SELECT t.typname
                FROM pg_type t
                JOIN pg_namespace n ON t.typnamespace = n.oid
                WHERE t.typtype = 'e'
                AND n.nspname = 'public'
            ");

            foreach ($types as $type) {
                DB::statement("DROP TYPE IF EXISTS \"{$type->typname}\" CASCADE");
            }
        } catch (\Throwable $e) {
            // Ignore if schema is empty or connection not ready
        }
    }

    private function usingPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }
}
