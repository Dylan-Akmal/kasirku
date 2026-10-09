<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { errorMessage } from '@/lib/errors'

const form = reactive({
  store_name: '',
  address: '',
  phone: '',
  receipt_footer: '',
})

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')

async function load() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/store-settings')
    const s = data.data

    Object.assign(form, {
      store_name: s.store_name,
      address: s.address ?? '',
      phone: s.phone ?? '',
      receipt_footer: s.receipt_footer ?? '',
    })
  } catch (e) {
    error.value = errorMessage(e, 'Gagal memuat pengaturan toko.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = ''
  success.value = ''

  try {
    await api.put('/store-settings', {
      store_name: form.store_name,
      address: form.address || null,
      phone: form.phone || null,
      receipt_footer: form.receipt_footer || null,
    })

    success.value = 'Pengaturan toko berhasil disimpan.'
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="text-xl font-bold">Pengaturan Toko</h1>
    <p class="mt-1 text-sm text-slate-500">Data ini tampil di bagian atas dan bawah struk.</p>

    <p v-if="loading" class="mt-6 text-sm text-slate-400">Memuat...</p>

    <div v-else class="mt-5 grid gap-5 lg:grid-cols-2">
      <form class="space-y-4 rounded-xl bg-white p-5 ring-1 ring-slate-200" @submit.prevent="save">
        <div>
          <label class="mb-1 block text-sm font-medium" for="s-name">Nama toko</label>
          <input
            id="s-name"
            v-model="form.store_name"
            required
            maxlength="150"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium" for="s-address">Alamat</label>
          <input
            id="s-address"
            v-model="form.address"
            maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium" for="s-phone">Nomor telepon</label>
          <input
            id="s-phone"
            v-model="form.phone"
            maxlength="30"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium" for="s-footer">Pesan kaki struk</label>
          <input
            id="s-footer"
            v-model="form.receipt_footer"
            maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
        <p v-if="success" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ success }}</p>

        <button
          type="submit"
          :disabled="saving"
          class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </form>

      <div class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
        <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Pratinjau struk</p>

        <div class="mx-auto mt-3 max-w-xs font-mono text-xs text-slate-800">
          <div class="text-center">
            <div class="text-sm font-bold">{{ form.store_name || 'Nama toko' }}</div>
            <div v-if="form.address">{{ form.address }}</div>
            <div v-if="form.phone">{{ form.phone }}</div>
          </div>

          <hr class="my-2 border-dashed border-slate-400" />
          <div class="text-center text-slate-400">... isi transaksi ...</div>
          <hr class="my-2 border-dashed border-slate-400" />

          <div v-if="form.receipt_footer" class="text-center">{{ form.receipt_footer }}</div>
        </div>
      </div>
    </div>
  </div>
</template>