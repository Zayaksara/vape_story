<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        // Auto-generate code jika kosong
        if (empty($this->code) && $this->name) {
            $this->merge([
                'code' => strtoupper(Str::slug($this->name, '_')),
            ]);
        }

        // Pastikan is_active selalu ada nilainya
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'code'              => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'code')
                    ->ignore($this->route('product')),
            ],
            'name'              => ['required', 'string', 'max:150'],
            'category_id'       => ['required', 'uuid', Rule::exists('categories', 'id')],
            'brand_id'          => ['nullable', 'uuid', Rule::exists('brands', 'id')],
            'base_price'        => ['required', 'numeric', 'min:0'],
            'image'             => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'is_active'         => ['boolean'],
            // Liquid / Device specific
            'flavor'            => ['nullable', 'string', 'max:100'],
            'size_ml'           => ['nullable', 'numeric', 'min:0'],
            'nicotine_strength' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'     => 'Kode produk wajib diisi.',
            'code.unique'       => 'Kode produk ini sudah digunakan.',
            'name.required'     => 'Nama produk wajib diisi.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists'   => 'Kategori tidak ditemukan.',
            'brand_id.exists'      => 'Brand tidak ditemukan.',
            'base_price.required'  => 'Harga dasar wajib diisi.',
            'base_price.min'       => 'Harga dasar tidak boleh negatif.',
            'image.image'       => 'File harus berupa gambar.',
            'image.mimes'       => 'Format gambar harus jpeg, png, jpg, atau webp.',
            'image.max'         => 'Ukuran gambar maksimal 2MB.',
        ];
    }
}