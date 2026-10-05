<?php

namespace App\Http\Requests\Admin;

use App\Models\Machine;
use App\Models\Site;
use App\Models\Stock;
use App\Models\User;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Sites (SPEC 4.1). The schema takes more sites without modification; codes are fixed once created.
 */
class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site ? $this->user()->can('update', $site) : $this->user()->can('create', Site::class);
    }

    public function rules(): array
    {
        /** @var Site|null $site */
        $site = $this->route('site');

        return [
            'code' => $site ? ['required', Rule::in([$site->code])] : ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('sites', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers(DateTimeZone::AMERICA))],
            'digest_hour' => ['required', 'integer', 'between:0,23'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Site|null $site */
            $site = $this->route('site');

            if (! $site || $this->boolean('is_active') || ! $site->is_active) {
                return;
            }

            // A site still holding stock, machines or people cannot simply disappear from the selectors.
            $blockers = array_filter([
                Stock::query()->where('site_id', $site->id)->where('qty', '>', 0)->exists() ? __('stock on hand') : null,
                Machine::query()->where('site_id', $site->id)->active()->exists() ? __('active machines') : null,
                User::query()->where('site_id', $site->id)->active()->exists() ? __('active users') : null,
            ]);

            if ($blockers) {
                $validator->errors()->add('is_active', __('The site still has :what. Move or deactivate those first.', ['what' => implode(', ', $blockers)]));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'code.in' => __('A site code cannot change once created.'),
            'code.regex' => __('Use capital letters and digits, e.g. BPC003.'),
            'state.regex' => __('Use the two-letter state code, e.g. TX.'),
            'timezone.in' => __('Choose a time zone from the list.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'state' => strtoupper(trim((string) $this->input('state'))),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
