<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        $rules = [
            'category_id' => ['integer', Rule::exists('categories', 'id')->where('vendor_id', app(TenantContext::class)->id())],

            // base SKU (product level)
            'base_sku' => ['required', 'string', Rule::unique('products', 'base_sku')->where('vendor_id', app(TenantContext::class)->id())],

            'title' => 'required|string',
            'description' => 'nullable|string',
            'uom' => 'required|string',

            'price' => 'required|numeric|decimal:0,2|min:0|max:9999999999.99',
            'sale_price' => 'required|numeric|decimal:0,2|min:0|max:9999999999.99',

            'status' => 'required|in:published,out-of-stock,inactive,draft',
            'stock' => 'required|integer|min:0',

            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->where('vendor_id', app(TenantContext::class)->id())],
            'currency' => ['nullable', Rule::in([app(TenantContext::class)->vendor?->currency])],

            'options' => 'nullable|array',

            // variants required on create
            'variants' => 'required|array|min:1',

            'variants.*.sku' => [
                'required',
                'string',
                'distinct', // no duplicates in request
                Rule::unique('product_variants', 'sku')->where('vendor_id', app(TenantContext::class)->id()), // unique in DB
            ],

            'variants.*.price' => 'required|numeric|decimal:0,2|min:0|max:9999999999.99',
            'variants.*.sale_price' => 'required|numeric|decimal:0,2|min:0|max:9999999999.99',
            'variants.*.stock' => 'required|integer|min:0',
            'variants.*.reserved_quantity' => 'nullable|numeric|min:0',
            'variants.*.attributes' => 'required|array',
        ];

        return $rules;
    }
}
