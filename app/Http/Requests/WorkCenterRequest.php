<?php

namespace App\Http\Requests;

use App\Models\WorkCenter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workCenter = $this->route('workCenter');

        return $workCenter
            ? $this->user()->can('update', $workCenter)
            : $this->user()->can('create', WorkCenter::class);
    }

    public function rules(): array
    {
        /** @var WorkCenter|null $workCenter */
        $workCenter = $this->route('workCenter');

        return [
            // A work centre keeps its site once it has consumption history; moving it would rewrite the past.
            'site_id' => $workCenter?->transactions()->exists()
                ? ['required', Rule::in([$workCenter->site_id])]
                : ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'machine_type_id' => ['nullable', 'integer', Rule::exists('machine_types', 'id')],
            'code' => ['required', 'string', 'max:30',
                Rule::unique('work_centers')->where('site_id', $this->input('site_id'))->ignore($workCenter)],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.in' => __('This work centre already has stock issued to it, so its site cannot change.'),
            'code.unique' => __('This code is already used at the selected site.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'machine_type_id' => $this->input('machine_type_id') ?: null,
        ]);
    }
}
