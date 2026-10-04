<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Customer checkout. Money fields are not accepted here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'currency' => ['nullable', Rule::in([app(TenantContext::class)->vendor?->currency])],
            'payment_method' => ['required', 'string', Rule::in([
                'cash', 'bank_transfer',
            ])],
            'shipping_method' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('vendor_id', app(TenantContext::class)->id())->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price_type' => ['required', 'string', Rule::in(['sale', 'original'])],
        ];
    }
}
