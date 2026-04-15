<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kasir dan admin boleh buat return
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'order_id'              => 'required|exists:orders,id|uuid',
            'reason'                => 'required|string|max:500',
            'notes'                 => 'nullable|string|max:255',
            'items'                 => 'required|array|min:1',
            'items.*.batch_id'      => 'required|exists:batches,id|uuid',
            'items.*.product_name'  => 'required|string|max:255',
            'items.*.quantity'      => 'required|integer|min:1',
            'items.*.unit_price'    => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'            => 'Minimal satu item harus di-return.',
            'items.*.batch_id.exists'   => 'Lot tidak ditemukan.',
            'items.*.quantity.min'      => 'Jumlah return minimal 1 unit.',
            'reason.required'           => 'Alasan return harus diisi.',
        ];
    }
}