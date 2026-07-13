<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait RefreshDatabaseWithPgEnums
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                // Drop and recreate schema to avoid FK deadlocks on PostgreSQL
                DB::statement('DROP SCHEMA public CASCADE');
                DB::statement('CREATE SCHEMA public');
                DB::statement('GRANT ALL ON SCHEMA public TO public');
            }

            $this->artisan('migrate:fresh');
            $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabaseWithPgEnums;
}
