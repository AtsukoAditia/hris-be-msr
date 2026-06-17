<?php

namespace App\Http\Requests\EmployeeDocument;

use App\Models\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['category', 'title', 'description'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);
            $values[$field] = $value === null || trim((string) $value) === ''
                ? null
                : trim((string) $value);
        }

        if ($this->has('labels') && is_array($this->input('labels'))) {
            $labels = collect($this->input('labels'))
                ->map(fn ($label) => trim((string) $label))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $values['labels'] = $labels === [] ? null : $labels;
        }

        if ($this->exists('is_confidential')) {
            $boolean = filter_var(
                $this->input('is_confidential'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );

            if ($boolean !== null) {
                $values['is_confidential'] = $boolean;
            }
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'required', Rule::in(array_keys(EmployeeDocument::CATEGORY_LABELS))],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'labels' => ['sometimes', 'nullable', 'array', 'max:10'],
            'labels.*' => ['required', 'string', 'max:50', 'distinct'],
            'issue_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => ['sometimes', 'nullable', 'date'],
            'is_confidential' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    ! $this->filled('expiry_date')
                    || $validator->errors()->has('expiry_date')
                    || $validator->errors()->has('issue_date')
                ) {
                    return;
                }

                $document = $this->route('employeeDocument');
                $issueDate = $this->input('issue_date', $document?->issue_date?->format('Y-m-d'));

                if ($issueDate && Carbon::parse($this->input('expiry_date'))->lt(Carbon::parse($issueDate))) {
                    $validator->errors()->add('expiry_date', 'Tanggal kedaluwarsa tidak boleh sebelum tanggal terbit.');
                }
            },
        ];
    }
}
