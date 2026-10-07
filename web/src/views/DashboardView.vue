<script setup>
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/api'

const auth = useAuthStore()
const store = ref(null)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/store-settings')
    store.value = data.data
  } catch {
    error.value = 'Gagal memuat data toko.'
  }
})
</script>

<template>
  <div>
    <h1 class="text-xl font-bold">Halo, {{ auth.user?.name }}</h1>
    <p class="mt-1 text-sm text-slate-500">
      Anda masuk sebagai {{ auth.isAdmin ? 'admin' : 'kasir' }}.
    </p>

    <div class="mt-5 max-w-md rounded-xl bg-white p-5 ring-1 ring-slate-200">
      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <template v-else-if="store">
        <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Toko</p>
        <p class="mt-1 text-lg font-semibold">{{ store.store_name }}</p>
        <p v-if="store.address" class="text-sm text-slate-500">{{ store.address }}</p>
        <p v-if="store.phone" class="text-sm text-slate-500">{{ store.phone }}</p>
      </template>

      <p v-else class="text-sm text-slate-400">Memuat...</p>
    </div>
  </div>
</template>