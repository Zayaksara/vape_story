<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Facades\Auth;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kasir dan admin bisa buat transaksi
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'payment_method'          => ['required', new Enum(PaymentMethod::class)],
            'paid_amount'             => 'required|numeric|min:0',
            'discount_amount'         => 'nullable|numeric|min:0',
            'tax_amount'              => 'nullable|numeric|min:0',
            'notes'                   => 'nullable|string|max:255',
            'idempotency_key'         => 'nullable|string|max:100',

            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id|uuid',
            'items.*.quantity'        => 'required|integer|min:1',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'              => 'Minimal satu item harus ada dalam transaksi.',
            'items.*.product_id.exists'   => 'Produk tidak ditemukan.',
            'items.*.quantity.min'        => 'Jumlah minimal 1 unit.',
            'items.*.unit_price.min'      => 'Harga tidak boleh negatif.',
            'payment_method.required'     => 'Metode pembayaran harus dipilih.',
            'paid_amount.required'        => 'Jumlah pembayaran harus diisi.',
        ];
    }
}