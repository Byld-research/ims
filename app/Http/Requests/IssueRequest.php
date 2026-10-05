<?php

namespace App\Http\Requests;

use App\Enums\ReasonCodeScope;
use App\Models\Machine;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Issue stock to a machine or generally (SPEC 5.2; screen 10).
 */
class IssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = Site::query()->find($this->input('site_id'));

        return $site !== null && $this->user()->can('issue', [Stock::class, $site]);
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'mode' => ['required', Rule::in(['machine', 'general'])],
            // A machine takes stock only at the site where it currently is (SPEC 5.8).
            'machine_id' => ['exclude_unless:mode,machine', 'required', 'integer', Rule::exists('machines', 'id')
                ->where('site_id', $this->input('site_id'))->where('is_active', true)],
            'reason_code_id' => ['exclude_unless:mode,general', 'required', 'integer', Rule::exists('reason_codes', 'id')
                ->where('applies_to', ReasonCodeScope::IssueGeneral->value)->where('is_active', true)],
            'qty' => ['required', 'regex:/^\d{1,11}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            // "Other, see note" means the note is the explanation.
            if ($this->input('mode') === 'general'
                && ReasonCode::query()->whereKey($this->input('reason_code_id'))->value('code') === 'OTHER'
                && blank($this->input('note'))) {
                $validator->errors()->add('note', __('Say what the stock was used for.'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => __('Pick an item from the list.'),
            'machine_id.required' => __('Choose the machine.'),
            'machine_id.exists' => __('That machine is not active at this site.'),
            'reason_code_id.required' => __('Choose what the stock was used for.'),
            'qty.regex' => __('Enter a positive quantity with at most 3 decimals.'),
            'qty.not_regex' => __('Enter a positive quantity with at most 3 decimals.'),
        ];
    }

    public function site(): Site
    {
        return Site::query()->findOrFail($this->validated('site_id'));
    }

    public function machine(): ?Machine
    {
        return $this->validated('mode') === 'machine' ? Machine::query()->findOrFail($this->validated('machine_id')) : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['qty' => trim((string) $this->input('qty'))]);
    }
}
