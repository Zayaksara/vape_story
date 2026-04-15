<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HANYA ADMIN yang boleh restock
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'product_id'    => 'required|exists:products,id|uuid',
            'lot_number'    => 'required|string|max:50|unique:batches,lot_number',
            'expired_date'  => 'required|date|after:today',
            'quantity'      => 'required|integer|min:1',
            'cost_price'    => 'required|numeric|min:0',
            'notes'         => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'lot_number.unique'     => 'Nomor batch ini sudah pernah digunakan.',
            'expired_date.after'    => 'Tanggal kadaluarsa harus setelah hari ini.',
            'quantity.min'          => 'Jumlah restock minimal 1 unit.',
            'cost_price.min'        => 'Harga modal tidak boleh negatif.',
        ];
    }
}