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
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $checkIn = $this->input('check_in_time');
            $checkOut = $this->input('check_out_time');

            if (! $checkIn && ! $checkOut) {
                $validator->errors()->add('check_in_time', 'Minimal salah satu waktu check-in atau check-out wajib diisi.');
            }

            if ($checkIn && $checkOut && $checkOut <= $checkIn) {
                $validator->errors()->add('check_out_time', 'Waktu check-out harus setelah check-in.');
            }
        });
    }
}
