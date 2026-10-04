<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            // Order Info
            'notes' => ['nullable', 'string', 'max:500'],
            'currency' => ['nullable', Rule::in([app(TenantContext::class)->vendor?->currency])],

            // Payment
            'payment_method' => ['nullable', 'string', Rule::in([
                'cash', 'credit_card', 'debit_card', 'gcash', 'paymaya', 'bank_transfer',
            ])],

            // Shipping
            'shipping_method' => ['nullable', 'string'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],

            // Discount
            'discount' => ['nullable', 'numeric', 'min:0'],

            // Tax
            'tax' => ['nullable', 'numeric', 'min:0'],

            // Order Items
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('vendor_id', app(TenantContext::class)->id())->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price_type' => ['required', 'string', Rule::in(['sale', 'original'])],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Order must have at least one item.',
            'items.min' => 'Order must have at least one item.',
            'items.*.variant_id.required' => 'Each item must have a valid variant.',
            'items.*.variant_id.exists' => 'One or more selected variants do not exist.',
            'items.*.quantity.required' => 'Each item must have a quantity.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.price_type.required' => 'Each item must have a price type.',
            'items.*.price_type.in' => 'Price type must be either sale or original.',
        ];
    }
}
