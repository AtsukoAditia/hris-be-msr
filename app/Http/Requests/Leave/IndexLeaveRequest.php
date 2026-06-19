<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class IndexLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:pending,approved,rejected,cancelled',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,id',
            'leave_type_id' => 'nullable|integer|exists:leave_types,id',
            'date_from' => 'nullable|date|after_or_equal:today',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status tidak valid.',
            'employee_id.exists' => 'Karyawan tidak ditemukan.',
            'employee_ids.array' => 'Daftar karyawan tidak valid.',
            'leave_type_id.exists' => 'Jenis cuti tidak ditemukan.',
            'date_from.date' => 'Format tanggal dari tidak valid.',
            'date_from.after_or_equal' => 'Tanggal dari harus hari ini atau setelahnya.',
            'date_to.date' => 'Format tanggal ke tidak valid.',
            'date_to.after_or_equal' => 'Tanggal ke harus sama atau setelah tanggal dari.',
            'per_page.integer' => 'Jumlah per halaman harus berupa angka.',
            'per_page.min' => 'Jumlah per halaman minimal 1.',
            'per_page.max' => 'Jumlah per halaman maksimal 100.',
        ];
    }
}
