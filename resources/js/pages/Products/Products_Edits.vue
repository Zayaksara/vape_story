<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    product: Object,
    categories: Array,
    brands: Array,
})

const form = useForm({
    _method: 'PUT', // Laravel method spoofing
    code: props.product.code,
    name: props.product.name,
    category_id: props.product.category_id,
    brand_id: props.product.brand_id ?? '',
    base_price: props.product.base_price,
    description: props.product.description ?? '',
    flavor: props.product.flavor ?? '',
    size_ml: props.product.size_ml ?? '',
    nicotine_strength: props.product.nicotine_strength ?? '',
    is_active: props.product.is_active,
    image: null,
})

const imagePreview = props.product.image
    ? `/storage/${props.product.image}`
    : null

function handleImage(e) {
    form.image = e.target.files[0]
}

function submit() {
    form.post(route('products.update', props.product.id), {
        forceFormData: true,
    })
}
</script>

<template>
    <div class="p-6 max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Produk</h1>

        <form @submit.prevent="submit" class="bg-white rounded shadow p-6 space-y-4">

            <!-- Kode & Nama -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kode Produk <span class="text-red-500">*</span></label>
                    <input v-model="form.code" type="text"
                           class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                           :class="{ 'border-red-400': form.errors.code }" />
                    <p v-if="form.errors.code" class="text-red-500 text-xs mt-1">{{ form.errors.code }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk <span class="text-red-500">*</span></label>
                    <input v-model="form.name" type="text"
                           class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                           :class="{ 'border-red-400': form.errors.name }" />
                    <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                </div>
            </div>

            <!-- Kategori & Brand -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                    <select v-model="form.category_id"
                            class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                            :class="{ 'border-red-400': form.errors.category_id }">
                        <option value="">-- Pilih Kategori --</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <p v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
                    <select v-model="form.brand_id"
                            class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="">-- Pilih Brand --</option>
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                </div>
            </div>

            <!-- Harga -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Harga Dasar <span class="text-red-500">*</span></label>
                <input v-model="form.base_price" type="number" min="0"
                       class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                       :class="{ 'border-red-400': form.errors.base_price }" />
                <p v-if="form.errors.base_price" class="text-red-500 text-xs mt-1">{{ form.errors.base_price }}</p>
            </div>

            <!-- Spesifikasi Liquid -->
            <div class="border rounded p-4 bg-gray-50">
                <p class="text-sm font-semibold text-gray-600 mb-3">Spesifikasi Liquid / Device (opsional)</p>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Rasa (Flavor)</label>
                        <input v-model="form.flavor" type="text"
                               class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ukuran (ml)</label>
                        <input v-model="form.size_ml" type="number" min="0"
                               class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nikotin (mg)</label>
                        <input v-model="form.nicotine_strength" type="number" min="0"
                               class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
                    </div>
                </div>
            </div>

            <!-- Deskripsi -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea v-model="form.description" rows="3"
                          class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
            </div>

            <!-- Gambar -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Produk</label>
                <div v-if="imagePreview && !form.image" class="mb-2">
                    <img :src="imagePreview" class="w-24 h-24 object-cover rounded border" alt="current image" />
                    <p class="text-xs text-gray-400 mt-1">Gambar saat ini. Upload baru untuk mengganti.</p>
                </div>
                <input type="file" accept="image/jpeg,image/png,image/jpg,image/webp"
                       @change="handleImage"
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                <p v-if="form.errors.image" class="text-red-500 text-xs mt-1">{{ form.errors.image }}</p>
            </div>

            <!-- Status -->
            <div class="flex items-center gap-2">
                <input type="checkbox" v-model="form.is_active" id="is_active"
                       class="w-4 h-4 rounded border-gray-300 text-blue-600" />
                <label for="is_active" class="text-sm text-gray-700">Produk Aktif</label>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 pt-2">
                <button type="submit" :disabled="form.processing"
                        class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm disabled:opacity-50">
                    {{ form.processing ? 'Menyimpan...' : 'Update Produk' }}
                </button>
                <a :href="route('products.index')"
                   class="px-6 py-2 border rounded text-sm text-gray-700 hover:bg-gray-50">
                    Batal
                </a>
            </div>

        </form>
    </div>
</template>