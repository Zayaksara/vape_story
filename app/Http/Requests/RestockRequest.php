<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'product_id'   => ['required', 'uuid', Rule::exists('products', 'id')],
            'lot_number'   => ['required', 'string', 'max:50', Rule::unique('batches', 'lot_number')],
            'expired_date' => ['required', 'date', 'after:today'],
            'quantity'     => ['required', 'integer', 'min:1'],
            'cost_price'   => ['required', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required'   => 'Produk wajib dipilih.',
            'product_id.exists'     => 'Produk tidak ditemukan.',
            'lot_number.required'   => 'Nomor lot wajib diisi.',
            'lot_number.unique'     => 'Nomor batch ini sudah pernah digunakan.',
            'expired_date.after'    => 'Tanggal kadaluarsa harus setelah hari ini.',
            'quantity.min'          => 'Jumlah restock minimal 1 unit.',
            'cost_price.min'        => 'Harga modal tidak boleh negatif.',
        ];
    }
}