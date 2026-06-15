<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Services\EmployeeDepartmentResolver;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDepartmentResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_supports_master_id_and_legacy_alias(): void
    {
        $this->seed(DepartmentSeeder::class);

        $resolver = app(EmployeeDepartmentResolver::class);
        $it = Department::where('code', 'IT')->firstOrFail();

        $this->assertSame('IT', $resolver->resolve(['department_id' => $it->id])->code);
        $this->assertSame('HR', $resolver->resolve(['department' => 'Human Resource'])->code);
    }
}
