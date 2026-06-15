<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeePositionResolver
{
    public function resolve(array $validated, Department $department): Position
    {
        if (! empty($validated['position_id'])) {
            $position = Position::query()
                ->active()
                ->find($validated['position_id']);

            if (! $position) {
                throw ValidationException::withMessages([
                    'position_id' => ['Jabatan yang dipilih tidak tersedia atau tidak aktif.'],
                ]);
            }

            if ((int) $position->department_id !== (int) $department->id) {
                throw ValidationException::withMessages([
                    'position_id' => ['Jabatan harus berasal dari departemen yang dipilih.'],
                ]);
            }

            return $position;
        }

        $legacyPosition = trim((string) ($validated['position'] ?? ''));
        $normalized = Str::lower(preg_replace('/\s+/', ' ', $legacyPosition) ?? $legacyPosition);

        $aliases = [
            'system administrator' => 'SYS-ADMIN',
            'hr staff' => 'HR-STAFF',
            'operational manager' => 'OPS-MANAGER',
            'operation manager' => 'OPS-MANAGER',
            'staff operation' => 'OPS-STAFF',
            'operations staff' => 'OPS-STAFF',
            'software engineer' => 'SOFTWARE-ENGINEER',
            'finance staff' => 'FIN-STAFF',
            'marketing staff' => 'MKT-STAFF',
        ];

        $position = Position::query()
            ->active()
            ->where('department_id', $department->id)
            ->where(function ($query) use ($aliases, $legacyPosition, $normalized) {
                if (isset($aliases[$normalized])) {
                    $query->where('code', $aliases[$normalized]);

                    return;
                }

                $query
                    ->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized])
                    ->orWhere('name', $legacyPosition);
            })
            ->first();

        if (! $position) {
            throw ValidationException::withMessages([
                'position' => ['Jabatan harus menggunakan master data aktif dari departemen yang dipilih.'],
            ]);
        }

        return $position;
    }

    public function legacyValue(Position $position, array $validated): string
    {
        if (! empty($validated['position_id'])) {
            return $position->code;
        }

        $submittedValue = trim((string) ($validated['position'] ?? ''));

        return $submittedValue !== '' ? $submittedValue : $position->code;
    }
}
