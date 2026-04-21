<?php
namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $name = $this->faker->company();  // ← balik pakai $this->faker

        return [
            'id'          => Str::uuid(),
            'name'        => $name,
            'slug'        => Str::slug($name),
            'description' => $this->faker->paragraph(2),
            'logo'        => null,
            'is_active'   => true,
        ];
    }
}