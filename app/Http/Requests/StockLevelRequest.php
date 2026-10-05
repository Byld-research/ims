<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Models\Site;
use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Bulk min level / bin / kanban settings for one site (SPEC 7, screen 6). Rows are keyed by item id.
 */
class StockLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = Site::query()->find($this->input('site_id'));

        return $site !== null && $this->user()->can('setLevels', [Stock::class, $site]);
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'rows' => ['required', 'array', 'max:200'],
            'rows.*.min_level' => ['nullable', 'decimal:0,3', 'min:0', 'max:99999999999'],
            'rows.*.bin' => ['nullable', 'string', 'max:40'],
            'rows.*.is_kanban' => ['boolean'],
            // Kanban needs a bin quantity (SPEC 4.6, 5.5).
            'rows.*.bin_qty' => ['nullable', 'required_if_accepted:rows.*.is_kanban', 'decimal:0,3', 'gt:0', 'max:99999999999'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $ids = array_map('intval', array_keys((array) $this->input('rows')));

            if (Item::query()->whereIn('id', $ids)->count() !== count(array_unique($ids))) {
                $validator->errors()->add('rows', __('Some rows refer to unknown items. Reload the page and try again.'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'rows.*.bin_qty.required_if_accepted' => __('A kanban item needs the quantity per bin.'),
            'rows.*.bin_qty.gt' => __('The quantity per bin must be greater than zero.'),
            'rows.*.min_level.min' => __('The minimum level cannot be negative.'),
        ];
    }

    public function site(): Site
    {
        return Site::query()->findOrFail($this->validated('site_id'));
    }

    protected function prepareForValidation(): void
    {
        $rows = [];

        foreach ((array) $this->input('rows') as $itemId => $row) {
            $rows[$itemId] = [
                'min_level' => trim((string) ($row['min_level'] ?? '')) ?: null,
                'bin' => trim((string) ($row['bin'] ?? '')) ?: null,
                'is_kanban' => filter_var($row['is_kanban'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'bin_qty' => trim((string) ($row['bin_qty'] ?? '')) ?: null,
            ];
        }

        $this->merge(['rows' => $rows]);
    }
}
