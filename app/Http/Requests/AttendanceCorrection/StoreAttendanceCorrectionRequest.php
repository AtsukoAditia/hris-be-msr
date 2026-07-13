<?php

namespace App\Http\Requests\AttendanceCorrection;

use App\Models\AttendanceCorrectionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['correction_type', 'reason'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }

        // Backward compatibility: accept correction_date as attendance_date
        if (! $this->has('attendance_date') && $this->has('correction_date')) {
            $values['attendance_date'] = $this->input('correction_date');
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'correction_type' => [
                'required',
                Rule::in([
                    AttendanceCorrectionRequest::TYPE_CHECK_IN,
                    AttendanceCorrectionRequest::TYPE_CHECK_OUT,
                    AttendanceCorrectionRequest::TYPE_BOTH,
                ]),
            ],
            'requested_check_in' => ['nullable', 'date_format:H:i'],
            'requested_check_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('correction_type');
            $checkIn = $this->input('requested_check_in');
            $checkOut = $this->input('requested_check_out');

            if (in_array($type, [AttendanceCorrectionRequest::TYPE_CHECK_IN, AttendanceCorrectionRequest::TYPE_BOTH], true) && ! $checkIn) {
                $validator->errors()->add('requested_check_in', 'Waktu check-in yang diminta wajib diisi.');
            }

            if (in_array($type, [AttendanceCorrectionRequest::TYPE_CHECK_OUT, AttendanceCorrectionRequest::TYPE_BOTH], true) && ! $checkOut) {
                $validator->errors()->add('requested_check_out', 'Waktu check-out yang diminta wajib diisi.');
            }

            if ($type === AttendanceCorrectionRequest::TYPE_BOTH && $checkIn && $checkOut && $checkOut <= $checkIn) {
                $validator->errors()->add('requested_check_out', 'Waktu check-out harus setelah check-in.');
            }
        });
    }
}
