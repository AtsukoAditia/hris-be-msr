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
            $table->foreignId('position_id')
                ->nullable()
                ->after('position')
                ->constrained('positions')
                ->nullOnDelete();
        });

        $this->backfillPositionIds();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
        });
    }

    private function backfillPositionIds(): void
    {
        DB::table('employees')
            ->whereNull('position_id')
            ->whereNotNull('position')
            ->whereNotNull('department_id')
            ->whereRaw("TRIM(position) <> ''")
            ->orderBy('id')
            ->get(['id', 'department_id', 'position'])
            ->each(function (object $employee): void {
                $positionName = trim(preg_replace('/\s+/', ' ', (string) $employee->position) ?? (string) $employee->position);
                $positionId = $this->resolvePositionId((int) $employee->department_id, $positionName);

                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update(['position_id' => $positionId]);
            });
    }

    private function resolvePositionId(int $departmentId, string $positionName): int
    {
        $existingPosition = DB::table('positions')
            ->where('department_id', $departmentId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($positionName)])
            ->first();

        if ($existingPosition) {
            return (int) $existingPosition->id;
        }

        return (int) DB::table('positions')->insertGetId([
            'department_id' => $departmentId,
            'code' => $this->generateUniqueCode($departmentId, $positionName),
            'name' => $positionName,
            'description' => 'Dibuat otomatis dari data jabatan karyawan lama.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    private function generateUniqueCode(int $departmentId, string $positionName): string
    {
        $departmentCode = (string) DB::table('departments')
            ->where('id', $departmentId)
            ->value('code');

        $words = array_values(array_filter(
            preg_split('/\s+/', Str::upper(Str::ascii($positionName))) ?: []
        ));

        $abbreviation = implode('', array_map(
            static fn (string $word): string => substr(preg_replace('/[^A-Z0-9]/', '', $word) ?? '', 0, 3),
            $words,
        ));

        $baseCode = trim(($departmentCode ?: 'POS').'-'.($abbreviation ?: 'ROLE'), '-');
        $baseCode = substr($baseCode, 0, 20);
        $candidate = $baseCode;
        $suffix = 2;

        while (DB::table('positions')->where('code', $candidate)->exists()) {
            $suffixText = '-'.$suffix;
            $candidate = substr($baseCode, 0, 20 - strlen($suffixText)).$suffixText;
            $suffix++;
        }

        return $candidate;
    }
};
