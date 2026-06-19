<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveRequest;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Leave::with(['employee.user', 'approver'])->latest('created_at');

        if (! $user->isAdmin() && ! $user->isManager()) {
            $query->whereHas('employee', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        if ($request->filled('status')) {
            $statuses = collect(explode(',', (string) $request->status))
                ->map(fn ($status) => trim($status))
                ->filter()
                ->values()
                ->all();

            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } else {
                $query->where('status', $statuses[0] ?? $request->status);
            }
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->date_from, $request->date_to])
                    ->orWhereBetween('end_date', [$request->date_from, $request->date_to]);
            });
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $leaves = $query->paginate($perPage);
        $leaves->getCollection()->transform(fn ($leave) => $this->transformLeave($leave));

        return response()->json([
            'success' => true,
            'message' => 'Data pengajuan cuti berhasil diambil.',
            'data' => $leaves,
        ]);
    }

    public function my(Request $request): JsonResponse
    {
        $employee = Auth::user()->employee;

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
                'data' => [],
            ], 404);
        }

        $query = Leave::with(['employee.user', 'approver'])
            ->where('employee_id', $employee->id)
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $leaves = $query->paginate($perPage);
        $leaves->getCollection()->transform(fn ($leave) => $this->transformLeave($leave));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat cuti berhasil diambil.',
            'data' => $leaves,
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $user = Auth::user();
        $employee = $user->employee;

        if ($request->filled('employee_id') && ($user->isAdmin() || $user->isManager())) {
            $employee = Employee::find($request->employee_id);
        }

        $year = (int) $request->get('year', now()->year);
        $total = 12;

        if (! $employee) {
            return response()->json([
                'success' => true,
                'message' => 'Saldo cuti default.',
                'data' => [
                    'total' => $total,
                    'used' => 0,
                    'remaining' => $total,
                    'year' => $year,
                ],
            ]);
        }

        $used = Leave::where('employee_id', $employee->id)
            ->where('leave_type', 'annual')
            ->where('status', Leave::STATUS_APPROVED)
            ->whereYear('start_date', $year)
            ->sum('total_days');

        return response()->json([
            'success' => true,
            'message' => 'Saldo cuti berhasil diambil.',
            'data' => [
                'employee_id' => $employee->id,
                'total' => $total,
                'used' => (int) $used,
                'remaining' => max(0, $total - (int) $used),
                'year' => $year,
            ],
        ]);
    }

    public function store(StoreLeaveRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $employee = Auth::user()->employee;

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        // Resolve leave type from leave_type_id (FK) or legacy leave_type string
        $leaveType = null;
        $leaveTypeCode = $validated['leave_type'] ?? null;

        if (isset($validated['leave_type_id'])) {
            $leaveType = LeaveType::find($validated['leave_type_id']);
            $leaveTypeCode = $leaveType ? $leaveType->code : null;
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $totalDays = max(1, $startDate->diffInDays($endDate) + 1);

        if ($leaveTypeCode === 'annual') {
            $balance = $this->getAnnualBalance($employee->id, (int) $startDate->year);

            if ($totalDays > $balance['remaining']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sisa cuti tahunan tidak mencukupi.',
                    'data' => $balance,
                ], 422);
            }
        }

        $overlappingLeave = Leave::where('employee_id', $employee->id)
            ->whereIn('status', [Leave::STATUS_PENDING, Leave::STATUS_APPROVED])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhere(function ($nested) use ($validated) {
                        $nested->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                    });
            })
            ->exists();

        if ($overlappingLeave) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah ada pengajuan cuti pada rentang tanggal tersebut.',
            ], 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                "leave-attachments/{$employee->id}",
                'public'
            );
        }

        $leave = Leave::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $validated['leave_type_id'] ?? null,
            'leave_type' => $leaveTypeCode,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'reason' => $validated['reason'],
            'attachment' => $attachmentPath,
            'status' => Leave::STATUS_PENDING,
        ]);

        $leave->load(['employee.user', 'approver', 'leaveType']);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti berhasil dikirim.',
            'data' => $this->transformLeave($leave),
        ], 201);
    }

    public function show(Leave $leave): JsonResponse
    {
        $leave->load(['employee.user', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Detail pengajuan cuti berhasil diambil.',
            'data' => $this->transformLeave($leave),
        ]);
    }

    public function approve(Request $request, Leave $leave): JsonResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        if (! $leave->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan cuti sudah tidak berstatus pending.',
            ], 422);
        }

        $leave->update([
            'status' => Leave::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $leave->load(['employee.user', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti disetujui.',
            'data' => $this->transformLeave($leave),
        ]);
    }

    public function reject(Request $request, Leave $leave): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        if (! $leave->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan cuti sudah tidak berstatus pending.',
            ], 422);
        }

        $leave->update([
            'status' => Leave::STATUS_REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $leave->load(['employee.user', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti ditolak.',
            'data' => $this->transformLeave($leave),
        ]);
    }

    public function destroy(Leave $leave): JsonResponse
    {
        $user = Auth::user();

        if (! $leave->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pengajuan pending yang bisa dibatalkan.',
            ], 422);
        }

        if (! $user->isAdmin() && $leave->employee->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak diizinkan.',
            ], 403);
        }

        $leave->update([
            'status' => Leave::STATUS_CANCELLED,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti berhasil dibatalkan.',
        ]);
    }

    private function getAnnualBalance(int $employeeId, int $year): array
    {
        $total = 12;
        $used = Leave::where('employee_id', $employeeId)
            ->where('leave_type', 'annual')
            ->where('status', Leave::STATUS_APPROVED)
            ->whereYear('start_date', $year)
            ->sum('total_days');

        return [
            'total' => $total,
            'used' => (int) $used,
            'remaining' => max(0, $total - (int) $used),
            'year' => $year,
        ];
    }

    private function transformLeave(Leave $leave): array
    {
        return [
            'id' => $leave->id,
            'employee_id' => $leave->employee_id,
            'leave_type' => $leave->leave_type,
            'leave_type_id' => $leave->leave_type_id,
            'leave_type_label' => $this->getLeaveTypeLabel($leave->leave_type),
            'start_date' => optional($leave->start_date)->format('Y-m-d'),
            'end_date' => optional($leave->end_date)->format('Y-m-d'),
            'total_days' => $leave->total_days,
            'reason' => $leave->reason,
            'attachment' => $leave->attachment,
            'attachment_url' => $leave->attachment ? Storage::disk('public')->url($leave->attachment) : null,
            'status' => $leave->status,
            'status_label' => $this->getStatusLabel($leave->status),
            'rejection_reason' => $leave->rejection_reason,
            'approved_by' => $leave->approved_by,
            'approved_at' => optional($leave->approved_at)->format('Y-m-d H:i:s'),
            'created_at' => optional($leave->created_at)->format('Y-m-d H:i:s'),
            'employee' => $leave->relationLoaded('employee') && $leave->employee ? [
                'id' => $leave->employee->id,
                'name' => $leave->employee->user->name ?? null,
                'email' => $leave->employee->user->email ?? null,
                'department' => $leave->employee->department ?? null,
                'position' => $leave->employee->position ?? null,
            ] : null,
            'approver' => $leave->relationLoaded('approver') && $leave->approver ? [
                'id' => $leave->approver->id,
                'name' => $leave->approver->name ?? null,
                'email' => $leave->approver->email ?? null,
            ] : null,
        ];
    }

    private function getLeaveTypeLabel(?string $type): string
    {
        return match ($type) {
            'annual' => 'Cuti Tahunan',
            'sick' => 'Cuti Sakit',
            'emergency' => 'Cuti Darurat',
            'maternity' => 'Cuti Melahirkan',
            'paternity' => 'Cuti Ayah',
            'unpaid' => 'Cuti Tidak Dibayar',
            'other' => 'Lainnya',
            default => 'Cuti',
        };
    }

    private function getStatusLabel(?string $status): string
    {
        return match ($status) {
            Leave::STATUS_PENDING => 'Menunggu',
            Leave::STATUS_APPROVED => 'Disetujui',
            Leave::STATUS_REJECTED => 'Ditolak',
            Leave::STATUS_CANCELLED => 'Dibatalkan',
            default => '-',
        };
    }
}
