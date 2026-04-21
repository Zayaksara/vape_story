<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
    products: Object,
    filters: Object,
    categories: Array,
    brands: Array,
})

const search = ref(props.filters?.search ?? '')
const category_id = ref(props.filters?.category_id ?? '')
const brand_id = ref(props.filters?.brand_id ?? '')

let timeout = null
watch([search, category_id, brand_id], () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get(route('products.index'), {
            search: search.value,
            category_id: category_id.value,
            brand_id: brand_id.value,
        }, { preserveState: true, replace: true })
    }, 400)
})

function destroy(id) {
    if (confirm('Yakin ingin menghapus produk ini?')) {
        router.delete(route('products.destroy', id))
    }
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Daftar Produk</h1>
            <a :href="route('products.create')"
               class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                + Tambah Produk
            </a>
        </div>

        <!-- Filter -->
        <div class="flex flex-wrap gap-3 mb-4">
            <input
                v-model="search"
                type="text"
                placeholder="Cari nama / kode..."
                class="border rounded px-3 py-2 text-sm w-60 focus:outline-none focus:ring-2 focus:ring-blue-400"
            />
            <select v-model="category_id" class="border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <option value="">Semua Kategori</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
            <select v-model="brand_id" class="border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <option value="">Semua Brand</option>
                <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto bg-white rounded shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">Gambar</th>
                        <th class="px-4 py-3 text-left">Kode</th>
                        <th class="px-4 py-3 text-left">Nama</th>
                        <th class="px-4 py-3 text-left">Kategori</th>
                        <th class="px-4 py-3 text-left">Brand</th>
                        <th class="px-4 py-3 text-left">Harga</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="products.data.length === 0">
                        <td colspan="8" class="text-center py-8 text-gray-400">Tidak ada produk ditemukan.</td>
                    </tr>
                    <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <img v-if="product.image"
                                 :src="`/storage/${product.image}`"
                                 class="w-12 h-12 object-cover rounded"
                                 alt="product image" />
                            <div v-else class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center text-gray-400 text-xs">
                                No Img
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-gray-600">{{ product.code }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ product.name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ product.category?.name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ product.brand?.name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">Rp {{ Number(product.base_price).toLocaleString('id-ID') }}</td>
                        <td class="px-4 py-3">
                            <span :class="product.is_active
                                ? 'bg-green-100 text-green-700'
                                : 'bg-red-100 text-red-700'"
                                class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 flex gap-2">
                            <a :href="route('products.edit', product.id)"
                               class="px-3 py-1 bg-yellow-400 text-white rounded text-xs hover:bg-yellow-500">
                                Edit
                            </a>
                            <button @click="destroy(product.id)"
                                    class="px-3 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600">
                                Hapus
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center mt-4 text-sm text-gray-600">
            <span>
                Menampilkan {{ products.from ?? 0 }}–{{ products.to ?? 0 }} dari {{ products.total }} produk
            </span>
            <div class="flex gap-1">
                <template v-for="link in products.links" :key="link.label">
                    <button
                        v-if="link.url"
                        @click="router.get(link.url, {}, { preserveState: true })"
                        :class="link.active ? 'bg-blue-600 text-white' : 'bg-white border text-gray-700 hover:bg-gray-50'"
                        class="px-3 py-1 rounded text-xs"
                        v-html="link.label"
                    />
                    <span v-else class="px-3 py-1 text-gray-400 text-xs" v-html="link.label" />
                </template>
            </div>
        </div>
    </div>
</template>