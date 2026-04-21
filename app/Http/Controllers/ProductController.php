<?php
namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Http\Requests\ProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['category', 'brand'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%")  // ilike untuk PostgreSQL
                      ->orWhere('code', 'ilike', "%{$search}%");
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->brand_id, function ($query, $brandId) {
                $query->where('brand_id', $brandId);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString(); // ← penting: supaya filter tetap ada saat pindah halaman

        return Inertia::render('Products/Index', [
            'products'   => $products,
            'filters'    => $request->only(['search', 'category_id', 'brand_id']),
            'categories' => Category::active()->get(['id', 'name']), // untuk dropdown filter
            'brands'     => Brand::active()->get(['id', 'name']),    // untuk dropdown filter
        ]);
    }

    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Category::active()->get(['id', 'name']),
            'brands'     => Brand::active()->get(['id', 'name']),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Products/Edit', [
            'product'    => $product->load(['category', 'brand']),
            'categories' => Category::active()->get(['id', 'name']),
            'brands'     => Brand::active()->get(['id', 'name']),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
}