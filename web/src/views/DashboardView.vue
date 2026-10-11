<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { dateTime, rupiah } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// locale sv-SE menghasilkan format YYYY-MM-DD sesuai tanggal lokal
const today = new Date().toLocaleDateString('sv-SE')

const store = ref(null)
const summary = ref(null)
const lowStock = ref([])
const lowStockTotal = ref(0)
const recent = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''

  const requests = [
    api.get('/store-settings'),
    api.get('/products', { params: { low_stock: 1, per_page: 5 } }),
    api.get('/transactions', { params: { per_page: 5 } }),
    auth.isAdmin
      ? api.get('/reports/sales', { params: { from: today, to: today } })
      : api.get('/transactions', {
          params: { from: today, to: today, status: 'completed', per_page: 100 },
        }),
  ]

  const [storeRes, lowRes, recentRes, todayRes] = await Promise.allSettled(requests)

  if (storeRes.status === 'fulfilled') {
    store.value = storeRes.value.data.data
  }

  if (lowRes.status === 'fulfilled') {
    lowStock.value = lowRes.value.data.data
    lowStockTotal.value = lowRes.value.data.total
  }

  if (recentRes.status === 'fulfilled') {
    recent.value = recentRes.value.data.data
  }

  if (todayRes.status === 'fulfilled') {
    const body = todayRes.value.data

    if (auth.isAdmin) {
      const s = body.data.summary
      summary.value = {
        transactions: s.transaction_count,
        revenue: s.revenue,
        profit: s.gross_profit,
        voided: s.voided_count,
      }
    } else {
      summary.value = {
        transactions: body.total,
        revenue: body.data.reduce((sum, t) => sum + t.total, 0),
      }
    }
  }

  if ([lowRes, recentRes, todayRes].some((r) => r.status === 'rejected')) {
    error.value = 'Sebagian data dashboard gagal dimuat.'
  }

  loading.value = false
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold">Halo, {{ auth.user?.name }}</h1>
        <p class="mt-1 text-sm text-slate-500">
          {{ store?.store_name || 'Kasirku' }}
          <span v-if="store?.address"> &middot; {{ store.address }}</span>
        </p>
      </div>

      <div class="flex gap-2">
        <RouterLink
          :to="{ name: 'pos' }"
          class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
        >
          Buka Kasir
        </RouterLink>
        <RouterLink
          :to="{ name: 'history' }"
          class="rounded-lg px-4 py-2 text-sm font-medium text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50"
        >
          Riwayat
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ error }}</p>

    <div v-if="summary" class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <template v-if="auth.isAdmin">
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Omzet hari ini</p>
          <p class="mt-1 text-lg font-bold text-indigo-600">{{ rupiah(summary.revenue) }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Transaksi</p>
          <p class="mt-1 text-lg font-bold">{{ summary.transactions }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Laba kotor</p>
          <p class="mt-1 text-lg font-bold text-emerald-600">{{ rupiah(summary.profit) }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Dibatalkan</p>
          <p class="mt-1 text-lg font-bold text-red-600">{{ summary.voided }}</p>
        </div>
      </template>

      <template v-else>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Transaksi saya hari ini</p>
          <p class="mt-1 text-lg font-bold">{{ summary.transactions }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <p class="text-xs text-slate-500">Total penjualan saya</p>
          <p class="mt-1 text-lg font-bold text-indigo-600">{{ rupiah(summary.revenue) }}</p>
        </div>
      </template>
    </div>

    <p v-else-if="loading" class="mt-5 text-sm text-slate-400">Memuat ringkasan...</p>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
      <section class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold">
            Stok menipis
            <span v-if="lowStockTotal > 0" class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">
              {{ lowStockTotal }}
            </span>
          </h2>
          <RouterLink
            :to="auth.isAdmin ? { name: 'stock' } : { name: 'products' }"
            class="text-xs text-indigo-600 hover:underline"
          >
            {{ auth.isAdmin ? 'Kelola stok' : 'Lihat produk' }}
          </RouterLink>
        </div>

        <p v-if="!loading && lowStock.length === 0" class="py-6 text-center text-sm text-slate-400">
          Semua stok aman.
        </p>

        <ul v-else class="mt-2 divide-y divide-slate-100">
          <li v-for="p in lowStock" :key="p.id" class="flex items-center justify-between py-2 text-sm">
            <span class="font-medium">{{ p.name }}</span>
            <span class="text-xs text-slate-500">
              Stok <span class="font-semibold text-red-600">{{ p.stock }}</span> / min {{ p.min_stock }}
            </span>
          </li>
        </ul>
      </section>

      <section class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold">Transaksi terbaru</h2>
          <RouterLink :to="{ name: 'history' }" class="text-xs text-indigo-600 hover:underline">
            Lihat semua
          </RouterLink>
        </div>

        <p v-if="!loading && recent.length === 0" class="py-6 text-center text-sm text-slate-400">
          Belum ada transaksi.
        </p>

        <ul v-else class="mt-2 divide-y divide-slate-100">
          <li v-for="t in recent" :key="t.id" class="flex items-center justify-between gap-3 py-2 text-sm">
            <div class="min-w-0">
              <div class="truncate font-medium">{{ t.invoice_no }}</div>
              <div class="text-xs text-slate-400">
                {{ dateTime(t.created_at) }}
                <span v-if="auth.isAdmin"> &middot; {{ t.user?.name }}</span>
              </div>
            </div>

            <div class="text-right">
              <div class="font-semibold">{{ rupiah(t.total) }}</div>
              <span
                class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                :class="t.status === 'voided' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-800'"
              >
                {{ t.status === 'voided' ? 'Dibatalkan' : 'Selesai' }}
              </span>
            </div>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>