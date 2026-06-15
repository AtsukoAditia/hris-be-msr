<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('department')
                ->constrained('departments')
                ->nullOnDelete();
        });

        $this->backfillDepartmentIds();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }

    private function backfillDepartmentIds(): void
    {
        DB::table('employees')
            ->whereNull('department_id')
            ->whereNotNull('department')
            ->whereRaw("TRIM(department) <> ''")
            ->orderBy('id')
            ->get(['id', 'department'])
            ->each(function (object $employee): void {
                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update([
                        'department_id' => $this->resolveDepartmentId((string) $employee->department),
                    ]);
            });
    }

    private function resolveDepartmentId(string $legacyDepartment): int
    {
        $legacyDepartment = trim(preg_replace('/\s+/', ' ', $legacyDepartment) ?? $legacyDepartment);
        $normalized = Str::lower($legacyDepartment);

        $aliases = [
            'it' => ['code' => 'IT', 'name' => 'Information Technology'],
            'information technology' => ['code' => 'IT', 'name' => 'Information Technology'],
            'hr' => ['code' => 'HR', 'name' => 'Human Resources'],
            'human resource' => ['code' => 'HR', 'name' => 'Human Resources'],
            'human resources' => ['code' => 'HR', 'name' => 'Human Resources'],
            'fin' => ['code' => 'FIN', 'name' => 'Finance'],
            'finance' => ['code' => 'FIN', 'name' => 'Finance'],
            'ops' => ['code' => 'OPS', 'name' => 'Operations'],
            'operation' => ['code' => 'OPS', 'name' => 'Operations'],
            'operations' => ['code' => 'OPS', 'name' => 'Operations'],
            'mgt' => ['code' => 'MGT', 'name' => 'Management'],
            'management' => ['code' => 'MGT', 'name' => 'Management'],
            'mkt' => ['code' => 'MKT', 'name' => 'Marketing'],
            'marketing' => ['code' => 'MKT', 'name' => 'Marketing'],
        ];

        if (isset($aliases[$normalized])) {
            return $this->firstOrCreateDepartment(
                $aliases[$normalized]['code'],
                $aliases[$normalized]['name'],
            );
        }

        $existingDepartment = DB::table('departments')
            ->whereRaw('LOWER(code) = ?', [$normalized])
            ->orWhereRaw('LOWER(name) = ?', [$normalized])
            ->first();

        if ($existingDepartment) {
            $this->restoreDepartment((int) $existingDepartment->id);

            return (int) $existingDepartment->id;
        }

        return $this->firstOrCreateDepartment(
            $this->generateUniqueCode($legacyDepartment),
            $legacyDepartment,
        );
    }

    private function firstOrCreateDepartment(string $code, string $name): int
    {
        $department = DB::table('departments')
            ->where('code', $code)
            ->first();

        if ($department) {
            $this->restoreDepartment((int) $department->id);

            return (int) $department->id;
        }

        return (int) DB::table('departments')->insertGetId([
            'code' => $code,
            'name' => $name,
            'description' => 'Dibuat otomatis dari data departemen karyawan lama.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    private function restoreDepartment(int $departmentId): void
    {
        DB::table('departments')
            ->where('id', $departmentId)
            ->update([
                'is_active' => true,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function generateUniqueCode(string $name): string
    {
        $asciiName = Str::upper(Str::ascii($name));
        $words = array_values(array_filter(preg_split('/\s+/', $asciiName) ?: []));

        if (count($words) > 1) {
            $baseCode = implode('', array_map(
                static fn (string $word): string => substr(preg_replace('/[^A-Z0-9]/', '', $word) ?? '', 0, 1),
                $words,
            ));
        } else {
            $baseCode = substr(preg_replace('/[^A-Z0-9]/', '', $asciiName) ?? '', 0, 3);
        }

        $baseCode = substr($baseCode ?: 'DEPT', 0, 20);
        $candidate = $baseCode;
        $suffix = 2;

        while (DB::table('departments')->where('code', $candidate)->exists()) {
            $suffixText = (string) $suffix;
            $candidate = substr($baseCode, 0, 20 - strlen($suffixText)).$suffixText;
            $suffix++;
        }

        return $candidate;
    }
};
