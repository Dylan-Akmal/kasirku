<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { errorMessage } from '@/lib/errors'
import { rupiah } from '@/lib/format'

const methodLabels = {
  cash: 'Tunai',
  qris: 'QRIS',
  transfer: 'Transfer',
}

function isoDate(date) {
  // locale sv-SE menghasilkan format YYYY-MM-DD sesuai tanggal lokal
  return date.toLocaleDateString('sv-SE')
}

function addDays(date, n) {
  const copy = new Date(date)
  copy.setDate(copy.getDate() + n)
  return copy
}

function parseDate(text) {
  const [y, m, d] = text.split('-').map(Number)
  return new Date(y, m - 1, d)
}

const from = ref(isoDate(addDays(new Date(), -6)))
const to = ref(isoDate(new Date()))
const sort = ref('quantity')

const report = ref(null)
const topProducts = ref([])
const loading = ref(false)
const error = ref('')
const exporting = ref(false)

async function load() {
  if (!from.value || !to.value) return

  loading.value = true
  error.value = ''

  try {
    const params = { from: from.value, to: to.value }

    const [sales, top] = await Promise.all([
      api.get('/reports/sales', { params }),
      api.get('/reports/top-products', {
        params: { ...params, sort: sort.value, limit: 10 },
      }),
    ])

    report.value = sales.data.data
    topProducts.value = top.data.data.products
  } catch (e) {
    error.value = errorMessage(e, 'Gagal memuat laporan.')
  } finally {
    loading.value = false
  }
}

watch([from, to, sort], load)

function preset(kind) {
  const now = new Date()

  if (kind === 'today') {
    from.value = isoDate(now)
    to.value = isoDate(now)
  } else if (kind === 'week') {
    from.value = isoDate(addDays(now, -6))
    to.value = isoDate(now)
  } else if (kind === 'month') {
    from.value = isoDate(new Date(now.getFullYear(), now.getMonth(), 1))
    to.value = isoDate(now)
  }
}

// Isi hari tanpa transaksi dengan nol agar grafik utuh (maksimal 62 hari)
const chartDays = computed(() => {
  if (!report.value) return []

  const byDate = new Map(report.value.by_day.map((d) => [d.date, d]))
  const start = parseDate(from.value)
  const end = parseDate(to.value)
  const span = Math.round((end - start) / 86400000) + 1

  if (span < 1 || span > 62) return report.value.by_day

  const days = []
  for (let i = 0; i < span; i++) {
    const key = isoDate(addDays(start, i))
    days.push(
      byDate.get(key) ?? {
        date: key,
        transaction_count: 0,
        revenue: 0,
        gross_profit: 0,
      },
    )
  }
  return days
})

const maxRevenue = computed(() => Math.max(...chartDays.value.map((d) => d.revenue), 1))

function barHeight(revenue) {
  if (revenue === 0) return 0
  return Math.max(Math.round((revenue / maxRevenue.value) * 100), 3)
}

function shortDate(text) {
  const [, month, day] = text.split('-')
  return `${day}/${month}`
}

async function exportCsv() {
  exporting.value = true
  error.value = ''

  try {
    const response = await api.get('/reports/export', {
      params: { from: from.value, to: to.value },
      responseType: 'blob',
    })

    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = `laporan-penjualan-${from.value}-sd-${to.value}.csv`
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    error.value = 'Gagal mengekspor CSV.'
  } finally {
    exporting.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-bold">Laporan Penjualan</h1>
      <button
        :disabled="exporting || !report"
        class="rounded-lg px-4 py-2 text-sm font-medium text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50 disabled:opacity-50"
        @click="exportCsv"
      >
        {{ exporting ? 'Mengekspor...' : 'Ekspor CSV' }}
      </button>
    </div>

    <div class="mt-4 flex flex-wrap items-end gap-3">
      <div>
        <label class="mb-1 block text-xs text-slate-500" for="r-from">Dari</label>
        <input
          id="r-from"
          v-model="from"
          type="date"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
        />
      </div>

      <div>
        <label class="mb-1 block text-xs text-slate-500" for="r-to">Sampai</label>
        <input
          id="r-to"
          v-model="to"
          type="date"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
        />
      </div>

      <button class="rounded-lg px-3 py-2 text-sm text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50" @click="preset('today')">
        Hari ini
      </button>
      <button class="rounded-lg px-3 py-2 text-sm text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50" @click="preset('week')">
        7 hari
      </button>
      <button class="rounded-lg px-3 py-2 text-sm text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50" @click="preset('month')">
        Bulan ini
      </button>
    </div>

    <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    <p v-if="loading && !report" class="mt-6 text-sm text-slate-400">Memuat laporan...</p>

    <template v-if="report">
      <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Omzet</p>
          <p class="mt-1 text-lg font-bold text-indigo-600">{{ rupiah(report.summary.revenue) }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Transaksi</p>
          <p class="mt-1 text-lg font-bold">{{ report.summary.transaction_count }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Laba kotor</p>
          <p class="mt-1 text-lg font-bold text-emerald-600">{{ rupiah(report.summary.gross_profit) }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Rata-rata transaksi</p>
          <p class="mt-1 text-lg font-bold">{{ rupiah(report.summary.average_transaction) }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Total diskon</p>
          <p class="mt-1 text-lg font-bold">{{ rupiah(report.summary.discount) }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Dibatalkan</p>
          <p class="mt-1 text-lg font-bold text-red-600">{{ report.summary.voided_count }}</p>
        </div>
      </div>

      <section class="mt-5 rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <h2 class="text-sm font-semibold">Omzet per hari</h2>

        <p v-if="chartDays.length === 0" class="py-8 text-center text-sm text-slate-400">
          Tidak ada transaksi pada rentang ini.
        </p>

        <div v-else class="mt-3 flex h-48 gap-1 overflow-x-auto">
          <div
            v-for="d in chartDays"
            :key="d.date"
            class="flex h-full min-w-8 flex-1 flex-col"
            :title="`${d.date}: ${rupiah(d.revenue)} (${d.transaction_count} transaksi)`"
          >
            <div class="flex flex-1 items-end">
              <div class="w-full rounded-t bg-indigo-500" :style="{ height: barHeight(d.revenue) + '%' }"></div>
            </div>
            <div class="mt-1 text-center text-[10px] text-slate-400">{{ shortDate(d.date) }}</div>
          </div>
        </div>
      </section>

      <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <section class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <h2 class="text-sm font-semibold">Per metode bayar</h2>
          <table class="mt-2 w-full text-left text-sm">
            <thead class="text-xs text-slate-500 uppercase">
              <tr>
                <th class="py-2">Metode</th>
                <th class="py-2">Transaksi</th>
                <th class="py-2 text-right">Omzet</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="report.by_payment_method.length === 0">
                <td colspan="3" class="py-4 text-center text-slate-400">Belum ada data.</td>
              </tr>
              <tr v-for="row in report.by_payment_method" :key="row.payment_method" class="border-t border-slate-100">
                <td class="py-2">{{ methodLabels[row.payment_method] }}</td>
                <td class="py-2">{{ row.transaction_count }}</td>
                <td class="py-2 text-right">{{ rupiah(row.revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <h2 class="text-sm font-semibold">Per kasir</h2>
          <table class="mt-2 w-full text-left text-sm">
            <thead class="text-xs text-slate-500 uppercase">
              <tr>
                <th class="py-2">Kasir</th>
                <th class="py-2">Transaksi</th>
                <th class="py-2 text-right">Omzet</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="report.by_cashier.length === 0">
                <td colspan="3" class="py-4 text-center text-slate-400">Belum ada data.</td>
              </tr>
              <tr v-for="row in report.by_cashier" :key="row.cashier" class="border-t border-slate-100">
                <td class="py-2">{{ row.cashier }}</td>
                <td class="py-2">{{ row.transaction_count }}</td>
                <td class="py-2 text-right">{{ rupiah(row.revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      <section class="mt-5 rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold">Produk terlaris</h2>
          <select
            v-model="sort"
            class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs outline-none focus:border-indigo-500"
          >
            <option value="quantity">Urut jumlah terjual</option>
            <option value="revenue">Urut omzet</option>
          </select>
        </div>

        <table class="mt-2 w-full text-left text-sm">
          <thead class="text-xs text-slate-500 uppercase">
            <tr>
              <th class="py-2">#</th>
              <th class="py-2">Produk</th>
              <th class="py-2">Terjual</th>
              <th class="py-2 text-right">Omzet</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="topProducts.length === 0">
              <td colspan="4" class="py-4 text-center text-slate-400">Belum ada data.</td>
            </tr>
            <tr v-for="(row, index) in topProducts" :key="row.product_id" class="border-t border-slate-100">
              <td class="py-2 text-slate-400">{{ index + 1 }}</td>
              <td class="py-2 font-medium">{{ row.product_name }}</td>
              <td class="py-2">{{ row.quantity_sold }}</td>
              <td class="py-2 text-right">{{ rupiah(row.revenue) }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>