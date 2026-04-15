<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RejectReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HANYA ADMIN yang boleh reject
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan penolakan harus diisi.',
        ];
    }
}