<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmployeeDocumentQueryService
{
    public function paginate(Request $request, ?Employee $employee = null): LengthAwarePaginator
    {
        $query = EmployeeDocument::query()
            ->with([
                'employee.user:id,name,email',
                'uploader:id,name,email',
            ]);

        if ($employee) {
            $query->where('employee_id', $employee->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', trim((string) $request->category));
        }

        if ($request->filled('status')) {
            $this->applyExpiryStatus(
                $query,
                trim((string) $request->status),
                $this->warningDays($request),
            );
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function (Builder $filter) use ($search): void {
                $filter->where('title', 'ilike', '%'.$search.'%')
                    ->orWhere('original_name', 'ilike', '%'.$search.'%')
                    ->orWhere('description', 'ilike', '%'.$search.'%')
                    ->orWhereHas('employee', function (Builder $employeeQuery) use ($search): void {
                        $employeeQuery->where('employee_number', 'ilike', '%'.$search.'%')
                            ->orWhereHas('user', fn (Builder $user) => $user
                                ->where('name', 'ilike', '%'.$search.'%')
                                ->orWhere('email', 'ilike', '%'.$search.'%'));
                    });
            });
        }

        match ($request->get('sort')) {
            'expiry_asc' => $query->orderByRaw('expiry_date IS NULL, expiry_date ASC'),
            'expiry_desc' => $query->orderByDesc('expiry_date'),
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $documents = $query->paginate($perPage);
        $documents->getCollection()->transform(fn (EmployeeDocument $document) => $this->transform($document, $this->warningDays($request)));

        return $documents;
    }

    public function summary(?Employee $employee = null, int $warningDays = 30): array
    {
        $base = EmployeeDocument::query();

        if ($employee) {
            $base->where('employee_id', $employee->id);
        }

        $today = Carbon::today();
        $warningDate = $today->copy()->addDays($warningDays);

        return [
            'total' => (clone $base)->count(),
            'valid' => (clone $base)->whereDate('expiry_date', '>', $warningDate)->count(),
            'expiring' => (clone $base)
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $warningDate)
                ->count(),
            'expired' => (clone $base)->whereDate('expiry_date', '<', $today)->count(),
            'without_expiry' => (clone $base)->whereNull('expiry_date')->count(),
            'warning_days' => $warningDays,
        ];
    }

    public function transform(EmployeeDocument $document, int $warningDays = 30): array
    {
        $document->loadMissing([
            'employee.user:id,name,email',
            'uploader:id,name,email',
        ]);

        return [
            'id' => $document->id,
            'employee_id' => $document->employee_id,
            'employee' => [
                'id' => $document->employee?->id,
                'employee_number' => $document->employee?->employee_number,
                'name' => $document->employee?->user?->name,
                'email' => $document->employee?->user?->email,
            ],
            'category' => $document->category,
            'category_label' => $document->categoryLabel(),
            'title' => $document->title,
            'description' => $document->description,
            'labels' => $document->labels ?? [],
            'file' => [
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'extension' => $document->extension,
                'size_bytes' => $document->size_bytes,
                'size_kb' => round($document->size_bytes / 1024, 2),
                'checksum_sha256' => $document->checksum_sha256,
                'version' => $document->version,
            ],
            'issue_date' => $document->issue_date?->format('Y-m-d'),
            'expiry_date' => $document->expiry_date?->format('Y-m-d'),
            'expiry_status' => $document->expiryStatus($warningDays),
            'days_until_expiry' => $document->daysUntilExpiry(),
            'is_confidential' => $document->is_confidential,
            'uploaded_by' => $document->uploader ? [
                'id' => $document->uploader->id,
                'name' => $document->uploader->name,
                'email' => $document->uploader->email,
            ] : null,
            'created_at' => $document->created_at?->toISOString(),
            'updated_at' => $document->updated_at?->toISOString(),
        ];
    }

    private function applyExpiryStatus(Builder $query, string $status, int $warningDays): void
    {
        $today = Carbon::today();
        $warningDate = $today->copy()->addDays($warningDays);

        match ($status) {
            'expired' => $query->whereDate('expiry_date', '<', $today),
            'expiring' => $query
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $warningDate),
            'valid' => $query->whereDate('expiry_date', '>', $warningDate),
            'without_expiry' => $query->whereNull('expiry_date'),
            default => null,
        };
    }

    private function warningDays(Request $request): int
    {
        return min(max((int) $request->get('expires_within_days', 30), 1), 365);
    }
}
