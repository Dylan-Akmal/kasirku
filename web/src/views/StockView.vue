<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { errorMessage } from '@/lib/errors'
import { dateTime } from '@/lib/format'
import BaseModal from '@/components/BaseModal.vue'

const tab = ref('stock')

// ---------- Tab 1: Stok produk ----------
const products = ref([])
const pMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const pLoading = ref(false)
const pError = ref('')
const q = ref('')
const lowStock = ref(false)
const pPage = ref(1)

async function loadProducts() {
  pLoading.value = true
  pError.value = ''

  try {
    const { data } = await api.get('/products', {
      params: {
        q: q.value || undefined,
        low_stock: lowStock.value ? 1 : undefined,
        page: pPage.value,
        per_page: 10,
      },
    })

    products.value = data.data
    pMeta.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      total: data.total,
    }
  } catch (e) {
    pError.value = errorMessage(e, 'Gagal memuat produk.')
  } finally {
    pLoading.value = false
  }
}

let timer
watch(q, () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    pPage.value = 1
    loadProducts()
  }, 300)
})

watch(lowStock, () => {
  pPage.value = 1
  loadProducts()
})

function goToProductPage(n) {
  if (n < 1 || n > pMeta.value.last_page) return
  pPage.value = n
  loadProducts()
}

// ---- Modal stok masuk ----
const inOpen = ref(false)
const inProduct = ref(null)
const inQty = ref(1)
const inNote = ref('')
const inSaving = ref(false)
const inError = ref('')

function openStockIn(p) {
  inProduct.value = p
  inQty.value = 1
  inNote.value = ''
  inError.value = ''
  inOpen.value = true
}

async function submitStockIn() {
  inSaving.value = true
  inError.value = ''

  try {
    await api.post(`/products/${inProduct.value.id}/stock-in`, {
      quantity: Number(inQty.value),
      note: inNote.value || null,
    })

    inOpen.value = false
    await loadProducts()
  } catch (e) {
    inError.value = errorMessage(e)
  } finally {
    inSaving.value = false
  }
}

// ---- Modal penyesuaian stok ----
const adjOpen = ref(false)
const adjProduct = ref(null)
const adjStock = ref(0)
const adjNote = ref('')
const adjSaving = ref(false)
const adjError = ref('')

function openAdjust(p) {
  adjProduct.value = p
  adjStock.value = p.stock
  adjNote.value = ''
  adjError.value = ''
  adjOpen.value = true
}

async function submitAdjust() {
  adjSaving.value = true
  adjError.value = ''

  try {
    await api.post(`/products/${adjProduct.value.id}/stock-adjust`, {
      new_stock: Number(adjStock.value),
      note: adjNote.value,
    })

    adjOpen.value = false
    await loadProducts()
  } catch (e) {
    adjError.value = errorMessage(e)
  } finally {
    adjSaving.value = false
  }
}

// ---------- Tab 2: Riwayat stok ----------
const typeLabels = {
  in: 'Stok masuk',
  out: 'Penjualan',
  adjustment: 'Penyesuaian',
  void_return: 'Kembali (void)',
}

const typeClasses = {
  in: 'bg-emerald-100 text-emerald-800',
  out: 'bg-amber-100 text-amber-800',
  adjustment: 'bg-sky-100 text-sky-800',
  void_return: 'bg-violet-100 text-violet-800',
}

const movements = ref([])
const mMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const mLoading = ref(false)
const mError = ref('')
const mPage = ref(1)

const productOptions = ref([])
const moveProduct = ref('')
const moveType = ref('')
const moveFrom = ref('')
const moveTo = ref('')

async function loadProductOptions() {
  try {
    const { data } = await api.get('/products', { params: { per_page: 100 } })
    productOptions.value = data.data
  } catch {
    // filter produk tetap kosong bila gagal
  }
}

async function loadMovements() {
  mLoading.value = true
  mError.value = ''

  try {
    const { data } = await api.get('/stock-movements', {
      params: {
        product_id: moveProduct.value || undefined,
        type: moveType.value || undefined,
        from: moveFrom.value || undefined,
        to: moveTo.value || undefined,
        page: mPage.value,
        per_page: 15,
      },
    })

    movements.value = data.data
    mMeta.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      total: data.total,
    }
  } catch (e) {
    mError.value = errorMessage(e, 'Gagal memuat riwayat stok.')
  } finally {
    mLoading.value = false
  }
}

watch([moveProduct, moveType, moveFrom, moveTo], () => {
  mPage.value = 1
  loadMovements()
})

function goToMovementPage(n) {
  if (n < 1 || n > mMeta.value.last_page) return
  mPage.value = n
  loadMovements()
}

function resetMovementFilters() {
  moveProduct.value = ''
  moveType.value = ''
  moveFrom.value = ''
  moveTo.value = ''
}

watch(tab, (value) => {
  if (value === 'history') {
    loadMovements()
  } else {
    loadProducts()
  }
})

onMounted(() => {
  loadProducts()
  loadProductOptions()
})
</script>

<template>
  <div>
    <h1 class="text-xl font-bold">Stok</h1>

    <div class="mt-4 flex gap-2 border-b border-slate-200">
      <button
        class="-mb-px border-b-2 px-4 py-2 text-sm font-medium"
        :class="tab === 'stock' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = 'stock'"
      >
        Stok Produk
      </button>
      <button
        class="-mb-px border-b-2 px-4 py-2 text-sm font-medium"
        :class="tab === 'history' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = 'history'"
      >
        Riwayat Stok
      </button>
    </div>

    <!-- ===== Tab Stok Produk ===== -->
    <div v-if="tab === 'stock'">
      <div class="mt-4 flex flex-wrap items-center gap-3">
        <input
          v-model="q"
          type="search"
          placeholder="Cari nama atau SKU..."
          class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 sm:w-64"
        />
        <label class="flex items-center gap-2 text-sm">
          <input v-model="lowStock" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          Stok menipis
        </label>
      </div>

      <p v-if="pError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ pError }}</p>

      <div class="mt-4 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
            <tr>
              <th class="px-4 py-3">Produk</th>
              <th class="px-4 py-3">Kategori</th>
              <th class="px-4 py-3">Stok</th>
              <th class="px-4 py-3">Batas Minimum</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="pLoading">
              <td colspan="5" class="px-4 py-6 text-center text-slate-400">Memuat...</td>
            </tr>
            <tr v-else-if="products.length === 0">
              <td colspan="5" class="px-4 py-6 text-center text-slate-400">Tidak ada produk.</td>
            </tr>

            <tr v-for="p in products" :key="p.id" class="border-t border-slate-100">
              <td class="px-4 py-3">
                <div class="font-medium">{{ p.name }}</div>
                <div class="text-xs text-slate-400">{{ p.sku || 'Tanpa SKU' }}</div>
              </td>
              <td class="px-4 py-3">{{ p.category?.name || '-' }}</td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="p.stock <= p.min_stock ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'"
                >
                  {{ p.stock }}
                </span>
              </td>
              <td class="px-4 py-3">{{ p.min_stock }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <button class="mr-3 text-indigo-600 hover:underline" @click="openStockIn(p)">Stok masuk</button>
                <button class="text-indigo-600 hover:underline" @click="openAdjust(p)">Penyesuaian</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
        <span>{{ pMeta.total }} produk</span>
        <div class="flex items-center gap-2">
          <button
            class="rounded-lg px-3 py-1.5 ring-1 ring-slate-200 hover:bg-slate-100 disabled:opacity-40"
            :disabled="pMeta.current_page <= 1"
            @click="goToProductPage(pMeta.current_page - 1)"
          >
            Sebelumnya
          </button>
          <span>Halaman {{ pMeta.current_page }} dari {{ pMeta.last_page }}</span>
          <button
            class="rounded-lg px-3 py-1.5 ring-1 ring-slate-200 hover:bg-slate-100 disabled:opacity-40"
            :disabled="pMeta.current_page >= pMeta.last_page"
            @click="goToProductPage(pMeta.current_page + 1)"
          >
            Berikutnya
          </button>
        </div>
      </div>
    </div>

    <!-- ===== Tab Riwayat Stok ===== -->
    <div v-else>
      <div class="mt-4 flex flex-wrap items-end gap-3">
        <select
          v-model="moveProduct"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
        >
          <option value="">Semua produk</option>
          <option v-for="p in productOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>

        <select
          v-model="moveType"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
        >
          <option value="">Semua jenis</option>
          <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
        </select>

        <div>
          <label class="mb-1 block text-xs text-slate-500" for="m-from">Dari</label>
          <input
            id="m-from"
            v-model="moveFrom"
            type="date"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
          />
        </div>

        <div>
          <label class="mb-1 block text-xs text-slate-500" for="m-to">Sampai</label>
          <input
            id="m-to"
            v-model="moveTo"
            type="date"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
          />
        </div>

        <button class="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-100" @click="resetMovementFilters">
          Reset
        </button>
      </div>

      <p v-if="mError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ mError }}</p>

      <div class="mt-4 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
            <tr>
              <th class="px-4 py-3">Waktu</th>
              <th class="px-4 py-3">Produk</th>
              <th class="px-4 py-3">Jenis</th>
              <th class="px-4 py-3">Perubahan</th>
              <th class="px-4 py-3">Stok Akhir</th>
              <th class="px-4 py-3">Oleh</th>
              <th class="px-4 py-3">Catatan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="mLoading">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">Memuat...</td>
            </tr>
            <tr v-else-if="movements.length === 0">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada riwayat stok.</td>
            </tr>

            <tr v-for="m in movements" :key="m.id" class="border-t border-slate-100">
              <td class="px-4 py-3 whitespace-nowrap">{{ dateTime(m.created_at) }}</td>
              <td class="px-4 py-3 font-medium">{{ m.product?.name }}</td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="typeClasses[m.type]">
                  {{ typeLabels[m.type] }}
                </span>
              </td>
              <td
                class="px-4 py-3 font-semibold"
                :class="m.quantity > 0 ? 'text-emerald-600' : 'text-red-600'"
              >
                {{ m.quantity > 0 ? `+${m.quantity}` : m.quantity }}
              </td>
              <td class="px-4 py-3">{{ m.stock_after }}</td>
              <td class="px-4 py-3">{{ m.user?.name }}</td>
              <td class="px-4 py-3 text-slate-500">{{ m.note || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
        <span>{{ mMeta.total }} catatan</span>
        <div class="flex items-center gap-2">
          <button
            class="rounded-lg px-3 py-1.5 ring-1 ring-slate-200 hover:bg-slate-100 disabled:opacity-40"
            :disabled="mMeta.current_page <= 1"
            @click="goToMovementPage(mMeta.current_page - 1)"
          >
            Sebelumnya
          </button>
          <span>Halaman {{ mMeta.current_page }} dari {{ mMeta.last_page }}</span>
          <button
            class="rounded-lg px-3 py-1.5 ring-1 ring-slate-200 hover:bg-slate-100 disabled:opacity-40"
            :disabled="mMeta.current_page >= mMeta.last_page"
            @click="goToMovementPage(mMeta.current_page + 1)"
          >
            Berikutnya
          </button>
        </div>
      </div>
    </div>

    <!-- Modal stok masuk -->
    <BaseModal :open="inOpen" :title="`Stok masuk: ${inProduct?.name || ''}`" @close="inOpen = false">
      <form class="space-y-4" @submit.prevent="submitStockIn">
        <p class="text-sm text-slate-500">Stok saat ini: <span class="font-semibold text-slate-800">{{ inProduct?.stock }}</span></p>

        <div>
          <label class="mb-1 block text-sm font-medium" for="in-qty">Jumlah masuk</label>
          <input
            id="in-qty"
            v-model.number="inQty"
            type="number"
            min="1"
            max="100000"
            required
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium" for="in-note">Catatan (opsional)</label>
          <input
            id="in-note"
            v-model="inNote"
            maxlength="255"
            placeholder="Contoh: restock dari supplier"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <p v-if="inError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ inError }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="inOpen = false">
            Batal
          </button>
          <button
            type="submit"
            :disabled="inSaving"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
          >
            {{ inSaving ? 'Menyimpan...' : 'Tambah stok' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal penyesuaian stok -->
    <BaseModal :open="adjOpen" :title="`Penyesuaian: ${adjProduct?.name || ''}`" @close="adjOpen = false">
      <form class="space-y-4" @submit.prevent="submitAdjust">
        <p class="text-sm text-slate-500">
          Stok tercatat: <span class="font-semibold text-slate-800">{{ adjProduct?.stock }}</span>.
          Isi stok sebenarnya hasil hitung fisik.
        </p>

        <div>
          <label class="mb-1 block text-sm font-medium" for="adj-stock">Stok sebenarnya</label>
          <input
            id="adj-stock"
            v-model.number="adjStock"
            type="number"
            min="0"
            required
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
          <p
            v-if="adjProduct && Number(adjStock) !== adjProduct.stock"
            class="mt-1 text-xs"
            :class="Number(adjStock) > adjProduct.stock ? 'text-emerald-600' : 'text-red-600'"
          >
            Selisih {{ Number(adjStock) - adjProduct.stock > 0 ? '+' : '' }}{{ Number(adjStock) - adjProduct.stock }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium" for="adj-note">Alasan penyesuaian</label>
          <input
            id="adj-note"
            v-model="adjNote"
            required
            maxlength="255"
            placeholder="Contoh: stock opname, 2 pcs rusak"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <p v-if="adjError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ adjError }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="adjOpen = false">
            Batal
          </button>
          <button
            type="submit"
            :disabled="adjSaving"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
          >
            {{ adjSaving ? 'Menyimpan...' : 'Simpan penyesuaian' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>