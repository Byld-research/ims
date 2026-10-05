<?php

namespace App\Http\Requests;

use App\Enums\Criticality;
use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item
            ? $this->user()->can('update', $item)
            : $this->user()->can('create', Item::class);
    }

    public function rules(): array
    {
        /** @var Item|null $item */
        $item = $this->route('item');

        $sku = ['required', 'string', 'max:40', Rule::unique('items', 'sku')->ignore($item)];

        if ($pattern = config('ims.sku_pattern')) {
            $sku[] = 'regex:'.$pattern;
        }

        // Immutable once the item has any stock transaction (SPEC 4.5).
        if ($item?->isSkuLocked()) {
            $sku[] = Rule::in([$item->sku]);
        }

        return [
            'sku' => $sku,
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_structural', false)],
            'uom' => ['required', 'string', 'max:20'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'mpn' => ['nullable', 'string', 'max:80'],
            'drawing_no' => ['nullable', 'string', 'max:80'],
            'criticality' => ['nullable', Rule::enum(Criticality::class)],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.in' => __('The SKU cannot be changed because the item already has stock movements.'),
            'sku.regex' => config('ims.sku_pattern_hint') ?: __('The SKU does not match the agreed numbering pattern.'),
            'category_id.exists' => __('Choose a category that items can be assigned to, not a top-level group.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => trim((string) $this->input('sku')),
            'uom' => trim((string) $this->input('uom')),
            'criticality' => $this->input('criticality') ?: null,
        ]);
    }
}
