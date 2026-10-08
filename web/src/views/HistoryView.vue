<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { errorMessage } from '@/lib/errors'
import { dateTime, rupiah } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/BaseModal.vue'
import ReceiptModal from '@/components/ReceiptModal.vue'

const auth = useAuthStore()

const methodLabels = {
  cash: 'Tunai',
  qris: 'QRIS',
  transfer: 'Transfer',
}

const transactions = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const error = ref('')

const q = ref('')
const from = ref('')
const to = ref('')
const status = ref('')
const method = ref('')
const page = ref(1)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/transactions', {
      params: {
        q: q.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        status: status.value || undefined,
        payment_method: method.value || undefined,
        page: page.value,
        per_page: 10,
      },
    })

    transactions.value = data.data
    meta.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      total: data.total,
    }
  } catch (e) {
    error.value = errorMessage(e, 'Gagal memuat riwayat transaksi.')
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

watch([from, to, status, method], () => {
  page.value = 1
  load()
})

function goToPage(n) {
  if (n < 1 || n > meta.value.last_page) return
  page.value = n
  load()
}

function setToday() {
  // locale sv-SE menghasilkan format YYYY-MM-DD sesuai tanggal lokal
  const today = new Date().toLocaleDateString('sv-SE')
  from.value = today
  to.value = today
}

function clearFilters() {
  q.value = ''
  from.value = ''
  to.value = ''
  status.value = ''
  method.value = ''
}

// ---- Detail transaksi ----
const detailOpen = ref(false)
const detail = ref(null)
const detailLoading = ref(false)
const detailError = ref('')

const voidMode = ref(false)
const voidReason = ref('')
const voiding = ref(false)

async function openDetail(t) {
  detailOpen.value = true
  detail.value = null
  detailError.value = ''
  voidMode.value = false
  voidReason.value = ''
  detailLoading.value = true

  try {
    const { data } = await api.get(`/transactions/${t.id}`)
    detail.value = data.data
  } catch (e) {
    detailError.value = errorMessage(e, 'Gagal memuat detail transaksi.')
  } finally {
    detailLoading.value = false
  }
}

async function submitVoid() {
  voiding.value = true
  detailError.value = ''

  try {
    const { data } = await api.post(`/transactions/${detail.value.id}/void`, {
      reason: voidReason.value,
    })

    detail.value = data.data
    voidMode.value = false
    voidReason.value = ''
    await load()
  } catch (e) {
    detailError.value = errorMessage(e)
  } finally {
    voiding.value = false
  }
}

// ---- Struk ----
const receipt = ref(null)
const receiptOpen = ref(false)

async function showReceipt() {
  detailError.value = ''

  try {
    const { data } = await api.get(`/transactions/${detail.value.id}/receipt`)
    receipt.value = data.data
    receiptOpen.value = true
  } catch (e) {
    detailError.value = errorMessage(e, 'Gagal memuat struk.')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="text-xl font-bold">Riwayat Transaksi</h1>
    <p v-if="!auth.isAdmin" class="mt-1 text-sm text-slate-500">Menampilkan transaksi yang Anda layani.</p>

    <div class="mt-4 flex flex-wrap items-end gap-3">
      <input
        v-model="q"
        type="search"
        placeholder="Cari nomor invoice..."
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 sm:w-56"
      />

      <div>
        <label class="mb-1 block text-xs text-slate-500" for="from">Dari</label>
        <input
          id="from"
          v-model="from"
          type="date"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
        />
      </div>

      <div>
        <label class="mb-1 block text-xs text-slate-500" for="to">Sampai</label>
        <input
          id="to"
          v-model="to"
          type="date"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
        />
      </div>

      <select
        v-model="status"
        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
      >
        <option value="">Semua status</option>
        <option value="completed">Selesai</option>
        <option value="voided">Dibatalkan</option>
      </select>

      <select
        v-model="method"
        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
      >
        <option value="">Semua metode</option>
        <option value="cash">Tunai</option>
        <option value="qris">QRIS</option>
        <option value="transfer">Transfer</option>
      </select>

      <button class="rounded-lg px-3 py-2 text-sm text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50" @click="setToday">
        Hari ini
      </button>
      <button class="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-100" @click="clearFilters">
        Reset
      </button>
    </div>

    <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-3">Invoice</th>
            <th class="px-4 py-3">Waktu</th>
            <th class="px-4 py-3">Kasir</th>
            <th class="px-4 py-3">Metode</th>
            <th class="px-4 py-3">Total</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-6 text-center text-slate-400">Memuat...</td>
          </tr>
          <tr v-else-if="transactions.length === 0">
            <td colspan="7" class="px-4 py-6 text-center text-slate-400">Tidak ada transaksi.</td>
          </tr>

          <tr v-for="t in transactions" :key="t.id" class="border-t border-slate-100">
            <td class="px-4 py-3 font-medium whitespace-nowrap">{{ t.invoice_no }}</td>
            <td class="px-4 py-3 whitespace-nowrap">{{ dateTime(t.created_at) }}</td>
            <td class="px-4 py-3">{{ t.user?.name }}</td>
            <td class="px-4 py-3">{{ methodLabels[t.payment_method] }}</td>
            <td class="px-4 py-3 whitespace-nowrap">{{ rupiah(t.total) }}</td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="t.status === 'voided' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-800'"
              >
                {{ t.status === 'voided' ? 'Dibatalkan' : 'Selesai' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button class="text-indigo-600 hover:underline" @click="openDetail(t)">Detail</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
      <span>{{ meta.total }} transaksi</span>
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

    <BaseModal :open="detailOpen" title="Detail Transaksi" @close="detailOpen = false">
      <p v-if="detailLoading" class="py-6 text-center text-sm text-slate-400">Memuat...</p>

      <div v-else-if="detail" class="space-y-4 text-sm">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="font-semibold">{{ detail.invoice_no }}</div>
            <div class="text-xs text-slate-500">{{ dateTime(detail.created_at) }}</div>
          </div>
          <span
            class="rounded-full px-2 py-0.5 text-xs font-medium"
            :class="detail.status === 'voided' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-800'"
          >
            {{ detail.status === 'voided' ? 'Dibatalkan' : 'Selesai' }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs text-slate-500">
          <div>Kasir: <span class="text-slate-800">{{ detail.user?.name }}</span></div>
          <div>Metode: <span class="text-slate-800">{{ methodLabels[detail.payment_method] }}</span></div>
        </div>

        <div class="overflow-x-auto rounded-lg ring-1 ring-slate-200">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-500 uppercase">
              <tr>
                <th class="px-3 py-2">Produk</th>
                <th class="px-3 py-2">Harga</th>
                <th class="px-3 py-2">Qty</th>
                <th class="px-3 py-2 text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in detail.items" :key="item.id" class="border-t border-slate-100">
                <td class="px-3 py-2">{{ item.product_name }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ rupiah(item.price_sell) }}</td>
                <td class="px-3 py-2">{{ item.quantity }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ rupiah(item.subtotal) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="space-y-1">
          <div class="flex justify-between">
            <span class="text-slate-500">Subtotal</span>
            <span>{{ rupiah(detail.subtotal) }}</span>
          </div>
          <div v-if="detail.discount_amount > 0" class="flex justify-between">
            <span class="text-slate-500">Diskon</span>
            <span class="text-red-600">-{{ rupiah(detail.discount_amount) }}</span>
          </div>
          <div class="flex justify-between text-base font-bold">
            <span>Total</span>
            <span>{{ rupiah(detail.total) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Dibayar</span>
            <span>{{ rupiah(detail.amount_paid) }}</span>
          </div>
          <div v-if="detail.payment_method === 'cash'" class="flex justify-between">
            <span class="text-slate-500">Kembalian</span>
            <span>{{ rupiah(detail.change_amount) }}</span>
          </div>
        </div>

        <div v-if="detail.status === 'voided'" class="rounded-lg bg-red-50 p-3 text-xs text-red-800">
          <div class="font-semibold">Transaksi dibatalkan</div>
          <div>Oleh: {{ detail.voided_by?.name || '-' }}</div>
          <div>Waktu: {{ dateTime(detail.voided_at) }}</div>
          <div>Alasan: {{ detail.void_reason }}</div>
        </div>

        <p v-if="detailError" class="rounded-lg bg-red-50 px-3 py-2 text-red-700">{{ detailError }}</p>

        <form v-if="voidMode" class="space-y-2 rounded-lg bg-slate-50 p-3" @submit.prevent="submitVoid">
          <label class="block text-xs font-medium" for="void-reason">
            Alasan pembatalan (stok barang akan dikembalikan)
          </label>
          <input
            id="void-reason"
            v-model="voidReason"
            required
            maxlength="255"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
          />
          <div class="flex justify-end gap-2">
            <button type="button" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-200" @click="voidMode = false">
              Batal
            </button>
            <button
              type="submit"
              :disabled="voiding"
              class="rounded-lg bg-red-600 px-3 py-1.5 font-semibold text-white hover:bg-red-700 disabled:opacity-60"
            >
              {{ voiding ? 'Memproses...' : 'Batalkan Transaksi' }}
            </button>
          </div>
        </form>

        <div v-if="!voidMode" class="flex justify-between gap-2">
          <button
            v-if="auth.isAdmin && detail.status === 'completed'"
            class="rounded-lg px-3 py-2 text-red-600 hover:bg-red-50"
            @click="voidMode = true"
          >
            Batalkan (void)
          </button>
          <span v-else />

          <button
            class="rounded-lg px-4 py-2 font-medium text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50"
            @click="showReceipt"
          >
            Lihat Struk
          </button>
        </div>
      </div>

      <p v-else-if="detailError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ detailError }}</p>
    </BaseModal>

    <ReceiptModal :open="receiptOpen" :receipt="receipt" @close="receiptOpen = false" />
  </div>
</template>