<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\ApproveLeaveRequest;
use App\Http\Requests\Leave\CancelLeaveRequest;
use App\Http\Requests\Leave\RejectLeaveRequest;
use App\Http\Requests\Leave\StoreLeaveRequest;
use App\Http\Resources\LeaveResource;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    public function __construct(
        protected LeaveService $leaveService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status', 'employee_id', 'leave_type_id', 'date_from', 'date_to', 'per_page',
        ]);

        // Route is protected by role:admin,hr,manager middleware.
        // Pass null so the service returns all leaves; filters narrow by employee_id if supplied.
        $leaves = $this->leaveService->list(null, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Data pengajuan cuti berhasil diambil.',
            'data' => LeaveResource::collection($leaves),
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

        $filters = $request->only(['status', 'per_page']);

        $leaves = $this->leaveService->list($employee, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat cuti berhasil diambil.',
            'data' => LeaveResource::collection($leaves),
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

        if (! $employee) {
            return response()->json([
                'success' => true,
                'message' => 'Saldo cuti default.',
                'data' => [
                    'total' => 12,
                    'used' => 0,
                    'remaining' => 12,
                    'year' => $year,
                ],
            ]);
        }

        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        $leaveTypes = LeaveType::where('is_active', true)->get();

        $balanceData = $leaveTypes->map(function ($lt) use ($balances) {
            $b = $balances->get($lt->id);

            return [
                'leave_type_id' => $lt->id,
                'leave_type_code' => $lt->code,
                'leave_type_name' => $lt->name,
                'total' => $b ? $b->opening_days : ($lt->max_days_per_year ?? 12),
                'used' => $b ? $b->used_days : 0,
                'remaining' => $b ? $b->remaining_days : ($lt->max_days_per_year ?? 12),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Saldo cuti berhasil diambil.',
            'data' => [
                'employee_id' => $employee->id,
                'year' => $year,
                'balances' => $balanceData,
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

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                "leave-attachments/{$employee->id}",
                'public'
            );
        }

        $data = [
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'attachment' => $attachmentPath,
        ];

        try {
            $leave = $this->leaveService->submit($employee, $data);
            $leave->load(['employee.user', 'approver', 'leaveType']);

            // Notify manager(s) of the leave request
            if ($employee->manager_id) {
                $manager = Employee::find($employee->manager_id);
                if ($manager) {
                    NotificationService::create(
                        $manager->user_id,
                        'leave_requested',
                        'Pengajuan Cuti Baru',
                        "{$employee->full_name} mengajukan cuti {$leave->leaveType?->name} ({$leave->start_date} — {$leave->end_date}).",
                        '📋',
                        '/approval',
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil dikirim.',
                'data' => new LeaveResource($leave),
            ], 201);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Leave $leave): JsonResponse
    {
        $leave->load(['employee.user', 'approver', 'leaveType']);

        return response()->json([
            'success' => true,
            'message' => 'Detail pengajuan cuti berhasil diambil.',
            'data' => new LeaveResource($leave),
        ]);
    }

    public function approve(ApproveLeaveRequest $request, Leave $leave): JsonResponse
    {
        try {
            $leave = $this->leaveService->approve($leave, $request->note);
            $leave->load(['employee.user', 'approver', 'leaveType']);

            NotificationService::create(
                $leave->employee->user_id,
                'leave_approved',
                'Cuti Disetujui',
                "Pengajuan cuti {$leave->leaveType?->name} ({$leave->start_date} — {$leave->end_date}) telah disetujui.",
                '✅',
                '/leave',
            );

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti disetujui.',
                'data' => new LeaveResource($leave),
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reject(RejectLeaveRequest $request, Leave $leave): JsonResponse
    {
        try {
            $leave = $this->leaveService->reject($leave, $request->rejection_reason);
            $leave->load(['employee.user', 'approver', 'leaveType']);

            NotificationService::create(
                $leave->employee->user_id,
                'leave_rejected',
                'Cuti Ditolak',
                "Pengajuan cuti {$leave->leaveType?->name} ({$leave->start_date} — {$leave->end_date}) ditolak: {$request->rejection_reason}",
                '❌',
                '/leave',
            );

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti ditolak.',
                'data' => new LeaveResource($leave),
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(CancelLeaveRequest $request, Leave $leave): JsonResponse
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

        try {
            $leave = $this->leaveService->cancel($leave);
            $leave->load(['employee.user', 'approver', 'leaveType']);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil dibatalkan.',
                'data' => new LeaveResource($leave),
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
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

        try {
            $this->leaveService->cancel($leave);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil dibatalkan.',
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
