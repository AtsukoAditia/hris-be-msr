<?php

namespace App\Http\Requests\Leave;

use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by controller
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $leaveType = LeaveType::where('id', $value)
                        ->where('is_active', true)
                        ->first();

                    if (! $leaveType) {
                        $fail('Jenis cuti tidak tersedia atau tidak aktif.');

                        return;
                    }

                    // Check gender restriction only when type is restricted to a specific gender.
                    $employee = auth()->user()->employee;
                    $restriction = $leaveType->gender_restriction;
                    if (in_array($restriction, ['male', 'female'], true) && $employee?->gender) {
                        if ($restriction !== $employee->gender) {
                            $fail('Jenis cuti ini hanya tersedia untuk '.($restriction === 'male' ? 'pria' : 'wanita').'.');
                        }
                    }

                    // Check if requires attachment
                    if ($leaveType->requires_attachment && ! $this->hasFile('attachment')) {
                        $fail('Jenis cuti ini memerlukan lampiran.');
                    }
                },
            ],
            'start_date' => [
                'required',
                'date',
                'after_or_equal:today',
                function ($attribute, $value, $fail) {
                    $employee = auth()->user()?->employee;
                    if ($employee?->join_date) {
                        $joinDate = Carbon::parse($employee->join_date);
                        $today = Carbon::now();
                        $monthsOfService = (int) $joinDate->diffInMonths($today);

                        if ($monthsOfService < 3) {
                            $fail('Anda baru bekerja '.$monthsOfService.' bulan. Minimal 3 bulan kerja untuk mengajukan cuti.');
                        }
                    }
                },
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function ($attribute, $value, $fail) {
                    $startDate = Carbon::parse($this->input('start_date'));
                    $endDate = Carbon::parse($value);

                    if ($startDate->diffInDays($endDate) > 90) {
                        $fail('Maksimal cuti 90 hari.');
                    }
                },
            ],
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120', // 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'Jenis cuti wajib diisi.',
            'leave_type_id.integer' => 'Jenis cuti tidak valid.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date' => 'Format tanggal mulai tidak valid.',
            'start_date.after_or_equal' => 'Tanggal mulai harus hari ini atau setelahnya.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.date' => 'Format tanggal selesai tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'reason.required' => 'Alasan wajib diisi.',
            'reason.max' => 'Alasan maksimal 1000 karakter.',
            'attachment.file' => 'Lampiran harus berupa file.',
            'attachment.mimes' => 'Lampiran harus berupa PDF, JPG, JPEG, PNG, DOC, atau DOCX.',
            'attachment.max' => 'Ukuran lampiran maksimal 5MB.',
        ];
    }
}
