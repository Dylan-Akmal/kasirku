<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const allMenu = [
  { label: 'Dashboard', to: { name: 'dashboard' } },
  { label: 'Kasir', to: { name: 'pos' } },
  { label: 'Riwayat', to: { name: 'history' } },
  { label: 'Produk', to: { name: 'products' } },
  { label: 'Kategori', to: { name: 'categories' }, adminOnly: true },
]

const menu = computed(() => allMenu.filter((item) => !item.adminOnly || auth.isAdmin))

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-screen flex-col md:flex-row">
    <aside class="border-b border-slate-200 bg-white md:w-60 md:border-r md:border-b-0">
      <div class="px-5 py-4 text-xl font-bold text-indigo-600">Kasirku</div>

      <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:flex-col md:pb-0">
        <RouterLink
          v-for="item in menu"
          :key="item.label"
          :to="item.to"
          class="rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100"
          active-class="!bg-indigo-50 !text-indigo-700"
        >
          {{ item.label }}
        </RouterLink>
      </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
        <div class="text-sm">
          <span class="font-semibold">{{ auth.user?.name }}</span>
          <span
            class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium"
            :class="auth.isAdmin ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
          >
            {{ auth.isAdmin ? 'Admin' : 'Kasir' }}
          </span>
        </div>

        <button
          class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
          @click="logout"
        >
          Keluar
        </button>
      </header>

      <main class="flex-1 p-5">
        <RouterView />
      </main>
    </div>
  </div>
</template>