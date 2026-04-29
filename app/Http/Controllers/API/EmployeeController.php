<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['user', 'shift']);

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'like', '%' . $search . '%')
                  ->orWhere('nik', 'like', '%' . $search . '%')
                  ->orWhere('department', 'like', '%' . $search . '%')
                  ->orWhere('position', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                  });
            });
        }

        $employees = $query->latest()->paginate($request->get('per_page', 15));
        $employees->getCollection()->transform(function ($employee) {
            return $this->transformEmployee($employee);
        });

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil diambil.',
            'data' => $employees,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'role' => 'required|string|in:admin,hr,manager,employee',
            'nik' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'nik')->whereNull('deleted_at'),
            ],
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'department' => 'required|string|max:100',
            'position' => 'required|string|max:100',
            'join_date' => 'required|date',
            'status' => 'nullable|string|in:active,inactive',
            'is_active' => 'nullable|boolean',
        ]);

        $employee = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make('password123'),
                'role' => $validated['role'],
                'is_active' => $this->resolveIsActive($validated),
            ]);

            return Employee::create([
                'user_id' => $user->id,
                'employee_number' => $this->generateEmployeeNumber($validated['department'], $user->id),
                'nik' => $validated['nik'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'department' => $validated['department'],
                'position' => $validated['position'],
                'join_date' => $validated['join_date'],
                'is_active' => $this->resolveIsActive($validated),
            ]);
        });

        $employee->load(['user', 'shift']);

        return response()->json([
            'success' => true,
            'message' => 'Karyawan berhasil ditambahkan.',
            'data' => $this->transformEmployee($employee),
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['user', 'shift']);

        return response()->json([
            'success' => true,
            'message' => 'Detail karyawan berhasil diambil.',
            'data' => $this->transformEmployee($employee),
        ]);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee->user_id),
            ],
            'role' => 'required|string|in:admin,hr,manager,employee',
            'nik' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'nik')
                    ->ignore($employee->id)
                    ->whereNull('deleted_at'),
            ],
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'department' => 'required|string|max:100',
            'position' => 'required|string|max:100',
            'join_date' => 'required|date',
            'status' => 'nullable|string|in:active,inactive',
            'is_active' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated, $employee) {
            $employee->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'is_active' => $this->resolveIsActive($validated),
            ]);

            $employee->update([
                'nik' => $validated['nik'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'department' => $validated['department'],
                'position' => $validated['position'],
                'join_date' => $validated['join_date'],
                'is_active' => $this->resolveIsActive($validated),
            ]);
        });

        $employee->refresh()->load(['user', 'shift']);

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil diperbarui.',
            'data' => $this->transformEmployee($employee),
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        DB::transaction(function () use ($employee) {
            $user = $employee->user;
            $employee->delete();

            if ($user) {
                $user->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil dihapus.',
            'data' => null,
        ]);
    }

    private function resolveIsActive(array $validated): bool
    {
        if (array_key_exists('is_active', $validated)) {
            return (bool) $validated['is_active'];
        }

        if (array_key_exists('status', $validated)) {
            return $validated['status'] === 'active';
        }

        return true;
    }

    private function transformEmployee(Employee $employee): Employee
    {
        $employee->formatted_employee_number = $this->formatEmployeeNumber(
            $employee->department,
            $employee->user_id,
            $employee->employee_number
        );

        return $employee;
    }

    private function formatEmployeeNumber(?string $department, ?int $userId, ?string $employeeNumber): string
    {
        if (!empty($employeeNumber)) {
            return $employeeNumber;
        }

        $prefix = strtoupper(substr($department ?? 'EMP', 0, 3));
        return sprintf('%s-%04d', $prefix, $userId ?? 0);
    }

    private function generateEmployeeNumber(string $department, int $userId): string
    {
        $prefix = strtoupper(substr($department, 0, 3));
        return sprintf('%s-%04d', $prefix, $userId);
    }
}
