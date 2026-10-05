<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReasonCodeScope;
use App\Models\ReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReasonCodeRequest extends FormRequest
{
    /** Codes the application posts itself (counts, opening balances): fixed and always active. */
    public const SYSTEM = [ReasonCode::COUNT, ReasonCode::OPENING];

    public function authorize(): bool
    {
        $code = $this->route('reasonCode');

        return $code ? $this->user()->can('update', $code) : $this->user()->can('create', ReasonCode::class);
    }

    public function rules(): array
    {
        /** @var ReasonCode|null $code */
        $code = $this->route('reasonCode');
        $system = $code && $code->applies_to === ReasonCodeScope::Adjustment && in_array($code->code, self::SYSTEM, true);

        return [
            'applies_to' => $system ? ['required', Rule::in([$code->applies_to->value])] : ['required', Rule::enum(ReasonCodeScope::class)],
            'code' => $system
                ? ['required', Rule::in([$code->code])]
                : ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/',
                    Rule::unique('reason_codes')->where('applies_to', $this->input('applies_to'))->ignore($code)],
            'label' => ['required', 'string', 'max:100'],
            'is_active' => $system ? ['accepted'] : ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => __('Use capital letters, digits and underscores, e.g. WATER_DAMAGE.'),
            'code.in' => __('This code is used by the application itself and cannot change.'),
            'applies_to.in' => __('This code is used by the application itself and cannot change.'),
            'is_active.accepted' => __('This code is used by the application itself and must stay active.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
