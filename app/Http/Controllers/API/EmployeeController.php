<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeDepartmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeDepartmentResolver $departmentResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['user', 'departmentMaster']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        } elseif ($request->filled('department')) {
            $department = trim((string) $request->department);

            $query->where(function ($departmentQuery) use ($department) {
                $departmentQuery
                    ->where('department', $department)
                    ->orWhereHas('departmentMaster', function ($masterQuery) use ($department) {
                        $masterQuery
                            ->where('code', $department)
                            ->orWhere('name', $department);
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'like', '%'.$search.'%')
                    ->orWhere('nik', 'like', '%'.$search.'%')
                    ->orWhere('department', 'like', '%'.$search.'%')
                    ->orWhere('position', 'like', '%'.$search.'%')
                    ->orWhere('employment_type', 'like', '%'.$search.'%')
                    ->orWhereHas('departmentMaster', function ($departmentQuery) use ($search) {
                        $departmentQuery
                            ->where('code', 'like', '%'.$search.'%')
                            ->orWhere('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $employees = $query->latest()->paginate($perPage);
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
        $validated = $this->validateEmployee($request);
        $department = $this->departmentResolver->resolve($validated);

        $employee = DB::transaction(function () use ($validated, $department) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make('password123'),
                'role' => $validated['role'],
                'is_active' => $this->resolveIsActive($validated),
            ]);

            return Employee::create([
                'user_id' => $user->id,
                'employee_number' => $this->generateEmployeeNumber($department->code, $user->id),
                'nik' => $validated['nik'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'department' => $this->departmentResolver->legacyValue(
                    $department,
                    $validated['department'] ?? null,
                ),
                'department_id' => $department->id,
                'position' => $validated['position'],
                'join_date' => $validated['join_date'],
                'employment_type' => $validated['employment_type'] ?? 'permanent',
                'is_active' => $this->resolveIsActive($validated),
            ]);
        });

        $employee->load(['user', 'departmentMaster']);

        return response()->json([
            'success' => true,
            'message' => 'Karyawan berhasil ditambahkan.',
            'data' => $this->transformEmployee($employee),
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['user', 'departmentMaster']);

        return response()->json([
            'success' => true,
            'message' => 'Detail karyawan berhasil diambil.',
            'data' => $this->transformEmployee($employee),
        ]);
    }

    public function profile(Employee $employee): JsonResponse
    {
        $employee->load(['user', 'departmentMaster']);

        return response()->json([
            'success' => true,
            'message' => 'Profil karyawan berhasil diambil.',
            'data' => $this->transformEmployee($employee),
        ]);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $this->validateEmployee($request, $employee);
        $department = $this->departmentResolver->resolve($validated);

        DB::transaction(function () use ($validated, $employee, $department) {
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
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'department' => $this->departmentResolver->legacyValue(
                    $department,
                    $validated['department'] ?? null,
                ),
                'department_id' => $department->id,
                'position' => $validated['position'],
                'join_date' => $validated['join_date'],
                'employment_type' => $validated['employment_type'] ?? 'permanent',
                'is_active' => $this->resolveIsActive($validated),
            ]);
        });

        $employee->refresh()->load(['user', 'departmentMaster']);

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil diperbarui.',
            'data' => $this->transformEmployee($employee),
        ]);
    }

    public function enrollFace(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'face_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($employee->face_image) {
            Storage::disk('public')->delete($employee->face_image);
        }

        $path = $validated['face_image']->store('face-enrollments', 'public');

        $employee->update([
            'face_image' => $path,
            'face_registered_at' => now(),
        ]);

        $employee->refresh()->load(['user', 'departmentMaster']);

        return response()->json([
            'success' => true,
            'message' => 'Foto wajah absensi berhasil disimpan.',
            'data' => $this->transformEmployee($employee),
        ]);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        if ((int) $employee->user_id === (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akun yang sedang digunakan tidak dapat dihapus.',
            ], 422);
        }

        DB::transaction(function () use ($employee) {
            $user = $employee->user;
            if ($employee->face_image) {
                Storage::disk('public')->delete($employee->face_image);
            }
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

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'role' => 'required|string|in:admin,hr,manager,employee',
            'nik' => ['required', 'string', 'max:50', Rule::unique('employees', 'nik')->ignore($employee?->id)->whereNull('deleted_at')],
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female',
            'department_id' => [
                'nullable',
                'integer',
                'required_without:department',
                Rule::exists('departments', 'id')->where(function ($query) {
                    $query->where('is_active', true)->whereNull('deleted_at');
                }),
            ],
            'department' => 'nullable|required_without:department_id|string|max:100',
            'position' => 'required|string|max:100',
            'join_date' => 'required|date',
            'employment_type' => 'nullable|string|in:permanent,contract,internship',
            'status' => 'nullable|string|in:active,inactive',
            'is_active' => 'nullable|boolean',
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
        $departmentCode = $employee->departmentMaster?->code;
        $employee->department_code = $departmentCode;
        $employee->department_name = $employee->departmentMaster?->name ?? $employee->department;
        $employee->formatted_employee_number = $this->formatEmployeeNumber(
            $departmentCode ?? $employee->department,
            $employee->user_id,
            $employee->employee_number,
        );
        $employee->face_image_url = $employee->face_image ? asset('storage/'.$employee->face_image) : null;
        $employee->is_face_registered = ! empty($employee->face_image);

        return $employee;
    }

    private function formatEmployeeNumber(?string $department, ?int $userId, ?string $employeeNumber): string
    {
        if (! empty($employeeNumber)) {
            return $employeeNumber;
        }

        $prefix = strtoupper(substr($department ?? 'EMP', 0, 3));

        return sprintf('%s-%04d', $prefix, $userId ?? 0);
    }

    private function generateEmployeeNumber(string $departmentCode, int $userId): string
    {
        $prefix = strtoupper(substr($departmentCode, 0, 3));

        return sprintf('%s-%04d', $prefix, $userId);
    }
}
