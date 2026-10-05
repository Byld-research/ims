<?php

namespace App\Http\Requests;

use App\Enums\Criticality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add lines by category, criticality class, kanban flag, due date or single item (SPEC 5.6.1).
 */
class StockCountLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('stockCount'));
    }

    public function rules(): array
    {
        return [
            'by' => ['required', Rule::in(['category', 'criticality', 'kanban', 'due', 'item'])],
            'category_id' => ['exclude_unless:by,category', 'required', 'integer', Rule::exists('categories', 'id')],
            'criticality' => ['exclude_unless:by,criticality', 'required', Rule::enum(Criticality::class)],
            'item_id' => ['exclude_unless:by,item', 'required', 'integer', Rule::exists('items', 'id')],
        ];
    }

    public function messages(): array
    {
        return ['item_id.required' => __('Pick an item from the list.')];
    }
}
