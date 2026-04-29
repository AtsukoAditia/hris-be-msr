<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
            $employee->formatted_employee_number = $this->formatEmployeeNumber($employee->department, $employee->user_id, $employee->employee_number);
            return $employee;
        });

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'nik' => 'required|string|max:50|unique:employees,nik',
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'join_date' => 'required|date',
            'role' => 'required|string|in:admin,hr,manager,employee',
            'status' => 'required|string|in:active,inactive',
        ]);

        $result = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make('password'),
                'role' => $validated['role'],
                'is_active' => $validated['status'] === 'active',
            ]);

            $rawEmployeeNumber = $this->generateEmployeeNumber();

            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_number' => $rawEmployeeNumber,
                'nik' => $validated['nik'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'department' => $validated['department'],
                'position' => $validated['position'],
                'join_date' => $validated['join_date'],
                'is_active' => $validated['status'] === 'active',
            ]);

            $employee = $employee->load(['user', 'shift']);
            $employee->formatted_employee_number = $this->formatEmployeeNumber($employee->department, $employee->user_id, $employee->employee_number);

            return $employee;
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully',
            'data' => $result,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $employee = Employee::with(['user', 'shift'])->findOrFail($id);
        $employee->formatted_employee_number = $this->formatEmployeeNumber($employee->department, $employee->user_id, $employee->employee_number);

        return response()->json([
            'success' => true,
            'data' => $employee,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $employee = Employee::with('user')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $employee->user_id,
            'nik' => 'required|string|max:50|unique:employees,nik,' . $employee->id,
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'join_date' => 'required|date',
            'role' => 'required|string|in:admin,hr,manager,employee',
            'status' => 'required|string|in:active,inactive',
        ]);

        $result = DB::transaction(function () use ($employee, $validated) {
            $employee->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'is_active' => $validated['status'] === 'active',
            ]);

            $employee->update([
                'nik' => $validated['nik'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'department' => $validated['department'],
                'position' => $validated['position'],
                'join_date' => $validated['join_date'],
                'is_active' => $validated['status'] === 'active',
            ]);

            $freshEmployee = $employee->fresh(['user', 'shift']);
            $freshEmployee->formatted_employee_number = $this->formatEmployeeNumber($freshEmployee->department, $freshEmployee->user_id, $freshEmployee->employee_number);

            return $freshEmployee;
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'data' => $result,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = Employee::with('user')->findOrFail($id);

        DB::transaction(function () use ($employee) {
            if ($employee->user) {
                $employee->user->delete();
            }

            $employee->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully',
        ]);
    }

    private function generateEmployeeNumber(): string
    {
        do {
            $employeeNumber = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Employee::where('employee_number', $employeeNumber)->exists());

        return $employeeNumber;
    }

    private function formatEmployeeNumber(?string $department, ?int $userId, ?string $employeeNumber): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $department), 0, 2));
        $prefix = $prefix !== '' ? $prefix : 'EM';
        $userIdPart = $userId ? (string) $userId : '';
        $employeeNumberPart = $employeeNumber ? str_pad((string) $employeeNumber, 5, '0', STR_PAD_LEFT) : '00000';

        return $prefix . '-' . $userIdPart . $employeeNumberPart;
    }
}
