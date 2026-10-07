<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { errorMessage } from '@/lib/errors'
import { rupiah } from '@/lib/format'
import ReceiptModal from '@/components/ReceiptModal.vue'

const methods = [
  { value: 'cash', label: 'Tunai' },
  { value: 'qris', label: 'QRIS' },
  { value: 'transfer', label: 'Transfer' },
]
const quickAmounts = [20000, 50000, 100000]

const products = ref([])
const categories = ref([])
const loading = ref(false)
const q = ref('')
const categoryId = ref('')
const searchInput = ref(null)

const cart = ref([])
const discountType = ref('none')
const discountValue = ref(0)
const paymentMethod = ref('cash')
const amountPaid = ref(null)

const submitting = ref(false)
const error = ref('')
const notice = ref('')

const receipt = ref(null)
const receiptOpen = ref(false)

let noticeTimer
function flash(message) {
  notice.value = message
  clearTimeout(noticeTimer)
  noticeTimer = setTimeout(() => (notice.value = ''), 3000)
}

async function loadCategories() {
  try {
    const { data } = await api.get('/categories')
    categories.value = data.data
  } catch {
    // filter kategori tetap kosong bila gagal
  }
}

async function loadProducts() {
  loading.value = true

  try {
    const { data } = await api.get('/products', {
      params: {
        q: q.value || undefined,
        category_id: categoryId.value || undefined,
        per_page: 30,
      },
    })
    products.value = data.data
  } catch (e) {
    error.value = errorMessage(e, 'Gagal memuat produk.')
  } finally {
    loading.value = false
  }
}

let searchTimer
watch(q, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadProducts, 300)
})
watch(categoryId, loadProducts)

// Scan barcode: pemindai mengetik SKU lalu menekan Enter
async function onSearchEnter() {
  const term = q.value.trim()
  if (!term) return

  try {
    const { data } = await api.get('/products', { params: { sku: term } })

    if (data.data.length === 1) {
      addToCart(data.data[0])
      q.value = ''
    } else {
      flash(`SKU "${term}" tidak ditemukan.`)
    }
  } catch {
    flash('Gagal mencari SKU.')
  }
}

// ---- Keranjang ----
function addToCart(product) {
  if (product.stock <= 0) return

  const existing = cart.value.find((item) => item.product.id === product.id)

  if (existing) {
    existing.product = product

    if (existing.quantity + 1 > product.stock) {
      flash(`Stok ${product.name} hanya ${product.stock}.`)
      return
    }

    existing.quantity += 1
    return
  }

  cart.value.push({ product, quantity: 1 })
}

function removeItem(item) {
  cart.value = cart.value.filter((i) => i !== item)
}

function changeQty(item, delta) {
  const next = item.quantity + delta

  if (next < 1) {
    removeItem(item)
    return
  }

  if (next > item.product.stock) {
    flash(`Stok ${item.product.name} hanya ${item.product.stock}.`)
    return
  }

  item.quantity = next
}

function setQty(item, event) {
  let n = parseInt(event.target.value, 10)

  if (Number.isNaN(n) || n < 1) n = 1

  if (n > item.product.stock) {
    n = item.product.stock
    flash(`Stok ${item.product.name} hanya ${item.product.stock}.`)
  }

  item.quantity = n
  event.target.value = n
}

const subtotal = computed(() =>
  cart.value.reduce((sum, item) => sum + item.product.price_sell * item.quantity, 0),
)

const discountInvalid = computed(() => {
  const value = Number(discountValue.value) || 0
  if (discountType.value === 'nominal') return value > subtotal.value
  if (discountType.value === 'percent') return value > 100
  return false
})

const discountAmount = computed(() => {
  const value = Number(discountValue.value) || 0
  if (discountInvalid.value) return 0
  if (discountType.value === 'nominal') return value
  if (discountType.value === 'percent') return Math.round((subtotal.value * value) / 100)
  return 0
})

const total = computed(() => subtotal.value - discountAmount.value)

const paid = computed(() =>
  paymentMethod.value === 'cash' ? Number(amountPaid.value) || 0 : total.value,
)

const change = computed(() => paid.value - total.value)

const canPay = computed(
  () =>
    cart.value.length > 0 &&
    !discountInvalid.value &&
    (paymentMethod.value !== 'cash' || paid.value >= total.value),
)

function resetCart() {
  cart.value = []
  discountType.value = 'none'
  discountValue.value = 0
  amountPaid.value = null
  error.value = ''
}

async function pay() {
  if (!canPay.value || submitting.value) return

  submitting.value = true
  error.value = ''

  const payload = {
    items: cart.value.map((item) => ({
      product_id: item.product.id,
      quantity: item.quantity,
    })),
    discount_type: discountType.value,
    discount_value: discountType.value === 'none' ? 0 : Number(discountValue.value) || 0,
    payment_method: paymentMethod.value,
  }

  if (paymentMethod.value === 'cash') {
    payload.amount_paid = paid.value
  }

  let created

  try {
    const { data } = await api.post('/transactions', payload)
    created = data.data
  } catch (e) {
    error.value = errorMessage(e, 'Transaksi gagal. Coba lagi.')
    await loadProducts()
    submitting.value = false
    return
  }

  // Transaksi sudah tercatat: kosongkan keranjang agar tidak terkirim dua kali
  resetCart()

  try {
    const result = await api.get(`/transactions/${created.id}/receipt`)
    receipt.value = result.data.data
    receiptOpen.value = true
  } catch {
    flash(`Transaksi ${created.invoice_no} berhasil, tetapi struk gagal dimuat.`)
  }

  await loadProducts()
  submitting.value = false
}

function closeReceipt() {
  receiptOpen.value = false
  nextTick(() => searchInput.value?.focus())
}

onMounted(() => {
  loadCategories()
  loadProducts()
  searchInput.value?.focus()
})
</script>

<template>
  <div class="grid gap-5 lg:grid-cols-3">
    <section class="lg:col-span-2">
      <div class="flex flex-wrap gap-3">
        <input
          ref="searchInput"
          v-model="q"
          type="search"
          placeholder="Cari produk, atau scan barcode lalu tekan Enter..."
          class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          @keydown.enter.prevent="onSearchEnter"
        />

        <select
          v-model="categoryId"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
        >
          <option value="">Semua kategori</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <p v-if="loading && products.length === 0" class="mt-6 text-sm text-slate-400">Memuat produk...</p>
      <p v-else-if="products.length === 0" class="mt-6 text-sm text-slate-400">Produk tidak ditemukan.</p>

      <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
        <button
          v-for="p in products"
          :key="p.id"
          type="button"
          :disabled="p.stock <= 0"
          class="rounded-xl bg-white p-3 text-left ring-1 ring-slate-200 hover:ring-indigo-400 disabled:cursor-not-allowed disabled:opacity-50"
          @click="addToCart(p)"
        >
          <img v-if="p.image_url" :src="p.image_url" alt="" class="h-20 w-full rounded-lg object-cover" />
          <div
            v-else
            class="flex h-20 w-full items-center justify-center rounded-lg bg-slate-100 text-xl font-semibold text-slate-300"
          >
            {{ p.name.charAt(0).toUpperCase() }}
          </div>

          <div class="mt-2 line-clamp-2 text-sm font-medium">{{ p.name }}</div>
          <div class="text-sm font-semibold text-indigo-600">{{ rupiah(p.price_sell) }}</div>
          <div class="text-xs" :class="p.stock <= p.min_stock ? 'text-red-600' : 'text-slate-400'">
            {{ p.stock <= 0 ? 'Stok habis' : `Stok ${p.stock}` }}
          </div>
        </button>
      </div>
    </section>

    <aside class="self-start rounded-2xl bg-white p-4 ring-1 ring-slate-200 lg:sticky lg:top-5">
      <h2 class="text-base font-semibold">Keranjang</h2>

      <p v-if="notice" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ notice }}</p>

      <p v-if="cart.length === 0" class="py-8 text-center text-sm text-slate-400">
        Belum ada item. Klik produk untuk menambahkan.
      </p>

      <ul v-else class="mt-2 max-h-72 divide-y divide-slate-100 overflow-y-auto">
        <li v-for="item in cart" :key="item.product.id" class="py-3">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="truncate text-sm font-medium">{{ item.product.name }}</div>
              <div class="text-xs text-slate-400">{{ rupiah(item.product.price_sell) }}</div>
            </div>
            <button class="text-xl leading-none text-slate-400 hover:text-red-600" @click="removeItem(item)">
              &times;
            </button>
          </div>

          <div class="mt-2 flex items-center justify-between">
            <div class="flex items-center gap-1">
              <button class="h-7 w-7 rounded-lg ring-1 ring-slate-300 hover:bg-slate-100" @click="changeQty(item, -1)">
                &minus;
              </button>
              <input
                type="number"
                min="1"
                :max="item.product.stock"
                :value="item.quantity"
                class="h-7 w-14 rounded-lg border border-slate-300 text-center text-sm outline-none focus:border-indigo-500"
                @change="setQty(item, $event)"
              />
              <button class="h-7 w-7 rounded-lg ring-1 ring-slate-300 hover:bg-slate-100" @click="changeQty(item, 1)">
                +
              </button>
            </div>

            <div class="text-sm font-semibold">{{ rupiah(item.product.price_sell * item.quantity) }}</div>
          </div>
        </li>
      </ul>

      <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
        <div class="flex justify-between text-sm">
          <span class="text-slate-500">Subtotal</span>
          <span>{{ rupiah(subtotal) }}</span>
        </div>

        <div>
          <div class="flex items-center gap-2">
            <select
              v-model="discountType"
              class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm outline-none focus:border-indigo-500"
            >
              <option value="none">Tanpa diskon</option>
              <option value="nominal">Diskon Rp</option>
              <option value="percent">Diskon %</option>
            </select>
            <input
              v-model.number="discountValue"
              type="number"
              min="0"
              :disabled="discountType === 'none'"
              class="w-full min-w-0 rounded-lg border border-slate-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 disabled:bg-slate-100"
            />
          </div>
          <p v-if="discountInvalid" class="mt-1 text-xs text-red-600">
            Diskon melebihi {{ discountType === 'percent' ? '100%' : 'subtotal' }}.
          </p>
        </div>

        <div v-if="discountAmount > 0" class="flex justify-between text-sm">
          <span class="text-slate-500">Diskon</span>
          <span class="text-red-600">-{{ rupiah(discountAmount) }}</span>
        </div>

        <div class="flex items-center justify-between">
          <span class="font-semibold">Total</span>
          <span class="text-xl font-bold text-indigo-600">{{ rupiah(total) }}</span>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="m in methods"
            :key="m.value"
            type="button"
            class="rounded-lg py-2 text-sm font-medium"
            :class="paymentMethod === m.value ? 'bg-indigo-600 text-white' : 'bg-white ring-1 ring-slate-300 hover:bg-slate-50'"
            @click="paymentMethod = m.value"
          >
            {{ m.label }}
          </button>
        </div>

        <div v-if="paymentMethod === 'cash'">
          <label class="mb-1 block text-xs font-medium text-slate-500" for="paid">Uang diterima</label>
          <input
            id="paid"
            v-model.number="amountPaid"
            type="number"
            min="0"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />

          <div class="mt-2 flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-lg px-3 py-1 text-xs ring-1 ring-slate-300 hover:bg-slate-100"
              @click="amountPaid = total"
            >
              Uang pas
            </button>
            <button
              v-for="n in quickAmounts"
              :key="n"
              type="button"
              class="rounded-lg px-3 py-1 text-xs ring-1 ring-slate-300 hover:bg-slate-100"
              @click="amountPaid = n"
            >
              {{ rupiah(n) }}
            </button>
          </div>

          <div v-if="cart.length > 0" class="mt-2 flex justify-between text-sm">
            <span class="text-slate-500">{{ change >= 0 ? 'Kembalian' : 'Kurang' }}</span>
            <span class="font-semibold" :class="change >= 0 ? 'text-emerald-600' : 'text-red-600'">
              {{ rupiah(Math.abs(change)) }}
            </span>
          </div>
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <button
          type="button"
          :disabled="!canPay || submitting"
          class="w-full rounded-lg bg-indigo-600 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
          @click="pay"
        >
          {{ submitting ? 'Memproses...' : `Bayar ${rupiah(total)}` }}
        </button>

        <button
          v-if="cart.length > 0"
          type="button"
          class="w-full text-center text-xs text-slate-400 hover:text-red-600"
          @click="resetCart"
        >
          Kosongkan keranjang
        </button>
      </div>
    </aside>

    <ReceiptModal :open="receiptOpen" :receipt="receipt" @close="closeReceipt" />
  </div>
</template>