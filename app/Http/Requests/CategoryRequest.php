<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', Category::class);
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            // Only one level of nesting (SPEC 4.4): a parent must itself be top level.
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id]))],
            'name' => ['required', 'string', 'max:100',
                Rule::unique('categories')->where('parent_id', $this->input('parent_id'))->ignore($category)],
            'is_structural' => ['boolean'],
            'default_bin' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $category = $this->route('category');

            if (! $category) {
                return;
            }

            if ($this->boolean('is_structural') && $category->items()->exists()) {
                $validator->errors()->add('is_structural', __('This category has items, so it cannot be made structural.'));
            }

            if ($this->filled('parent_id') && $category->children()->exists()) {
                $validator->errors()->add('parent_id', __('This category has subcategories, so it cannot be placed under another category.'));
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'parent_id' => $this->input('parent_id') ?: null,
        ]);
    }
}
