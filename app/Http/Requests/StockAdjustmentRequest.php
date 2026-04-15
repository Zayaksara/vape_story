<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HANYA ADMIN yang boleh adjustment
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'batch_id'  => 'required|exists:batches,id|uuid',
            'quantity'  => 'required|integer|min:1',        // Positif saja di form
            'notes'     => 'required|string|max:255',       // Wajib alasan
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min'   => 'Jumlah adjustment minimal 1 unit.',
            'notes.required' => 'Alasan adjustment harus diisi.',
        ];
    }
}