<?php

namespace App\Http\Requests\EmployeeDocument;

use App\Models\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['category', 'title', 'description'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $values[$field] = $value === null || trim((string) $value) === ''
                    ? null
                    : trim((string) $value);
            }
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

        if (! $this->exists('is_confidential')) {
            $values['is_confidential'] = true;
        } else {
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
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'category' => ['required', Rule::in(array_keys(EmployeeDocument::CATEGORY_LABELS))],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'labels' => ['nullable', 'array', 'max:10'],
            'labels.*' => ['required', 'string', 'max:50', 'distinct'],
            'issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'is_confidential' => ['required', 'boolean'],
        ];
    }
}
