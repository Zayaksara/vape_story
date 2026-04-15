<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class RejectReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HANYA ADMIN yang boleh reject
        return Auth::check() && Auth::user()->isAdmin();
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