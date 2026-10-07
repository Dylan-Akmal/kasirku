<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { errorMessage } from '@/lib/errors'
import { rupiah } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/BaseModal.vue'

const auth = useAuthStore()

const products = ref([])
const categories = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const error = ref('')

const q = ref('')
const categoryId = ref('')
const lowStock = ref(false)
const page = ref(1)

async function loadCategories() {
  try {
    const { data } = await api.get('/categories')
    categories.value = data.data
  } catch {
    // filter kategori tetap kosong bila gagal
  }
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/products', {
      params: {
        q: q.value || undefined,
        category_id: categoryId.value || undefined,
        low_stock: lowStock.value ? 1 : undefined,
        page: page.value,
        per_page: 10,
      },
    })

    products.value = data.data
    meta.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      total: data.total,
    }
  } catch (e) {
    error.value = errorMessage(e, 'Gagal memuat produk.')
  } finally {
    loading.value = false
  }
}

let timer
watch(q, () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
})

watch([categoryId, lowStock], () => {
  page.value = 1
  load()
})

function goToPage(n) {
  if (n < 1 || n > meta.value.last_page) return
  page.value = n
  load()
}

// ---- Form tambah / ubah ----
const formOpen = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  name: '',
  sku: '',
  category_id: null,
  price_buy: 0,
  price_sell: 0,
  stock: 0,
  min_stock: 5,
})

function openCreate() {
  editingId.value = null
  Object.assign(form, {
    name: '',
    sku: '',
    category_id: null,
    price_buy: 0,
    price_sell: 0,
    stock: 0,
    min_stock: 5,
  })
  formError.value = ''
  formOpen.value = true
}

function openEdit(p) {
  editingId.value = p.id
  Object.assign(form, {
    name: p.name,
    sku: p.sku || '',
    category_id: p.category_id,
    price_buy: p.price_buy,
    price_sell: p.price_sell,
    stock: p.stock,
    min_stock: p.min_stock,
  })
  formError.value = ''
  formOpen.value = true
}

async function save() {
  saving.value = true
  formError.value = ''

  const payload = {
    name: form.name,
    sku: form.sku || null,
    category_id: form.category_id || null,
    price_buy: Number(form.price_buy),
    price_sell: Number(form.price_sell),
    min_stock: Number(form.min_stock),
  }

  try {
    if (editingId.value) {
      await api.put(`/products/${editingId.value}`, payload)
    } else {
      await api.post('/products', { ...payload, stock: Number(form.stock) })
    }

    formOpen.value = false
    await load()
  } catch (e) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove(p) {
  if (!window.confirm(`Hapus produk "${p.name}"? Riwayat transaksi lama tetap tersimpan.`)) return

  try {
    await api.delete(`/products/${p.id}`)

    if (products.value.length === 1 && page.value > 1) {
      page.value -= 1
    }

    await load()
  } catch (e) {
    error.value = errorMessage(e, 'Gagal menghapus produk.')
  }
}

// ---- Foto produk ----
const photoOpen = ref(false)
const photoProduct = ref(null)
const photoFile = ref(null)
const photoPreview = ref('')
const photoBusy = ref(false)
const photoError = ref('')

function openPhoto(p) {
  photoProduct.value = p
  photoFile.value = null
  photoPreview.value = ''
  photoError.value = ''
  photoOpen.value = true
}

function onPhotoSelected(event) {
  const file = event.target.files[0]
  photoFile.value = file || null
  photoPreview.value = file ? URL.createObjectURL(file) : ''
}

async function uploadPhoto() {
  if (!photoFile.value) return

  photoBusy.value = true
  photoError.value = ''

  const body = new FormData()
  body.append('image', photoFile.value)

  try {
    await api.post(`/products/${photoProduct.value.id}/image`, body)
    photoOpen.value = false
    await load()
  } catch (e) {
    photoError.value = errorMessage(e)
  } finally {
    photoBusy.value = false
  }
}

async function deletePhoto() {
  photoBusy.value = true
  photoError.value = ''

  try {
    await api.delete(`/products/${photoProduct.value.id}/image`)
    photoOpen.value = false
    await load()
  } catch (e) {
    photoError.value = errorMessage(e)
  } finally {
    photoBusy.value = false
  }
}

onMounted(() => {
  loadCategories()
  load()
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold">Produk</h1>
      <button
        v-if="auth.isAdmin"
        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
        @click="openCreate"
      >
        Tambah Produk
      </button>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
      <input
        v-model="q"
        type="search"
        placeholder="Cari nama atau SKU..."
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 sm:w-64"
      />

      <select
        v-model="categoryId"
        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
      >
        <option value="">Semua kategori</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>

      <label class="flex items-center gap-2 text-sm">
        <input v-model="lowStock" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
        Stok menipis
      </label>
    </div>

    <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-3">Foto</th>
            <th class="px-4 py-3">Produk</th>
            <th class="px-4 py-3">Kategori</th>
            <th class="px-4 py-3">Harga Jual</th>
            <th v-if="auth.isAdmin" class="px-4 py-3">Harga Beli</th>
            <th class="px-4 py-3">Stok</th>
            <th v-if="auth.isAdmin" class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-6 text-center text-slate-400">Memuat...</td>
          </tr>
          <tr v-else-if="products.length === 0">
            <td colspan="7" class="px-4 py-6 text-center text-slate-400">Tidak ada produk.</td>
          </tr>

          <tr v-for="p in products" :key="p.id" class="border-t border-slate-100">
            <td class="px-4 py-3">
              <img v-if="p.image_url" :src="p.image_url" alt="" class="h-10 w-10 rounded-lg object-cover" />
              <div
                v-else
                class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-400"
              >
                {{ p.name.charAt(0).toUpperCase() }}
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="font-medium">{{ p.name }}</div>
              <div class="text-xs text-slate-400">{{ p.sku || 'Tanpa SKU' }}</div>
            </td>
            <td class="px-4 py-3">{{ p.category?.name || '-' }}</td>
            <td class="px-4 py-3">{{ rupiah(p.price_sell) }}</td>
            <td v-if="auth.isAdmin" class="px-4 py-3">{{ rupiah(p.price_buy) }}</td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="p.stock <= p.min_stock ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'"
              >
                {{ p.stock }}
              </span>
            </td>
            <td v-if="auth.isAdmin" class="px-4 py-3 text-right whitespace-nowrap">
              <button class="mr-3 text-indigo-600 hover:underline" @click="openEdit(p)">Ubah</button>
              <button class="mr-3 text-indigo-600 hover:underline" @click="openPhoto(p)">Foto</button>
              <button class="text-red-600 hover:underline" @click="remove(p)">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
      <span>{{ meta.total }} produk</span>
      <div class="flex items-center gap-2">
        <button
          class="rounded-lg px-3 py-1.5 ring-1 ring-slate-200 hover:bg-slate-100 disabled:opacity-40"
          :disabled="meta.current_page <= 1"
          @click="goToPage(meta.current_page - 1)"
        >
          Sebelumnya
        </button>
        <span>Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
        <button
          class="rounded-lg px-3 py-1.5 ring-1 ring-slate-200 hover:bg-slate-100 disabled:opacity-40"
          :disabled="meta.current_page >= meta.last_page"
          @click="goToPage(meta.current_page + 1)"
        >
          Berikutnya
        </button>
      </div>
    </div>

    <BaseModal :open="formOpen" :title="editingId ? 'Ubah Produk' : 'Tambah Produk'" @close="formOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1 block text-sm font-medium" for="p-name">Nama produk</label>
          <input
            id="p-name"
            v-model="form.name"
            required
            maxlength="150"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-sm font-medium" for="p-sku">SKU / barcode</label>
            <input
              id="p-sku"
              v-model="form.sku"
              maxlength="50"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium" for="p-category">Kategori</label>
            <select
              id="p-category"
              v-model="form.category_id"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500"
            >
              <option :value="null">Tanpa kategori</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-sm font-medium" for="p-buy">Harga beli</label>
            <input
              id="p-buy"
              v-model.number="form.price_buy"
              type="number"
              min="0"
              required
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium" for="p-sell">Harga jual</label>
            <input
              id="p-sell"
              v-model.number="form.price_sell"
              type="number"
              min="0"
              required
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div v-if="!editingId">
            <label class="mb-1 block text-sm font-medium" for="p-stock">Stok awal</label>
            <input
              id="p-stock"
              v-model.number="form.stock"
              type="number"
              min="0"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium" for="p-min">Batas stok menipis</label>
            <input
              id="p-min"
              v-model.number="form.min_stock"
              type="number"
              min="0"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            />
          </div>
        </div>

        <p v-if="editingId" class="text-xs text-slate-400">
          Stok tidak diubah di sini. Gunakan fitur stok masuk atau penyesuaian stok.
        </p>

        <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="formOpen = false">
            Batal
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
          >
            {{ saving ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <BaseModal :open="photoOpen" :title="`Foto: ${photoProduct?.name || ''}`" @close="photoOpen = false">
      <div class="space-y-4">
        <div class="flex justify-center">
          <img
            v-if="photoPreview || photoProduct?.image_url"
            :src="photoPreview || photoProduct.image_url"
            alt=""
            class="h-48 w-48 rounded-xl object-cover ring-1 ring-slate-200"
          />
          <div
            v-else
            class="flex h-48 w-48 items-center justify-center rounded-xl bg-slate-100 text-sm text-slate-400"
          >
            Belum ada foto
          </div>
        </div>

        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="block w-full text-sm"
          @change="onPhotoSelected"
        />
        <p class="text-xs text-slate-400">Format JPG, PNG, atau WEBP, maksimal 2 MB.</p>

        <p v-if="photoError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ photoError }}</p>

        <div class="flex justify-between gap-2">
          <button
            v-if="photoProduct?.image_url"
            type="button"
            :disabled="photoBusy"
            class="rounded-lg px-4 py-2 text-sm text-red-600 hover:bg-red-50 disabled:opacity-60"
            @click="deletePhoto"
          >
            Hapus foto
          </button>
          <span v-else />

          <button
            type="button"
            :disabled="!photoFile || photoBusy"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
            @click="uploadPhoto"
          >
            {{ photoBusy ? 'Memproses...' : 'Unggah' }}
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>