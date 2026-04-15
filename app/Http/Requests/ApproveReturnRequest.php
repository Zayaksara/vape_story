<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ApproveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HANYA ADMIN yang boleh approve
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:255',
        ];
    }
}