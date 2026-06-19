<?php

namespace App\Http\Requests\AttendanceCorrection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ManualCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        if (is_string($reason)) {
            $this->merge(['reason' => trim($reason)]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'correction_type' => ['required', 'string', 'in:check_in,check_out,both'],
            'requested_check_in' => ['nullable', 'required_if:correction_type,check_in,both', 'date_format:H:i'],
            'requested_check_out' => ['nullable', 'required_if:correction_type,check_out,both', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $correctionType = $this->input('correction_type');
            $checkIn = $this->input('requested_check_in');
            $checkOut = $this->input('requested_check_out');

            if ($correctionType === 'both' && (! $checkIn || ! $checkOut)) {
                $validator->errors()->add('requested_check_in', 'Untuk koreksi keduanya, check-in dan check-out wajib diisi.');
            }

            if ($checkIn && $checkOut && $checkOut <= $checkIn) {
                $validator->errors()->add('requested_check_out', 'Waktu check-out harus setelah check-in.');
            }
        });
    }
}
