<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { errorMessage } from '@/lib/errors'
import BaseModal from '@/components/BaseModal.vue'

const categories = ref([])
const loading = ref(false)
const error = ref('')

const modalOpen = ref(false)
const editingId = ref(null)
const name = ref('')
const saving = ref(false)
const formError = ref('')

async function load() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/categories')
    categories.value = data.data
  } catch (e) {
    error.value = errorMessage(e, 'Gagal memuat kategori.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  name.value = ''
  formError.value = ''
  modalOpen.value = true
}

function openEdit(category) {
  editingId.value = category.id
  name.value = category.name
  formError.value = ''
  modalOpen.value = true
}

async function save() {
  saving.value = true
  formError.value = ''

  try {
    if (editingId.value) {
      await api.put(`/categories/${editingId.value}`, { name: name.value })
    } else {
      await api.post('/categories', { name: name.value })
    }

    modalOpen.value = false
    await load()
  } catch (e) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove(category) {
  const note =
    category.products_count > 0
      ? ` ${category.products_count} produk di dalamnya akan menjadi tanpa kategori.`
      : ''

  if (!window.confirm(`Hapus kategori "${category.name}"?${note}`)) return

  try {
    await api.delete(`/categories/${category.id}`)
    await load()
  } catch (e) {
    error.value = errorMessage(e, 'Gagal menghapus kategori.')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold">Kategori</h1>
      <button
        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
        @click="openCreate"
      >
        Tambah Kategori
      </button>
    </div>

    <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Jumlah Produk</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="3" class="px-4 py-6 text-center text-slate-400">Memuat...</td>
          </tr>
          <tr v-else-if="categories.length === 0">
            <td colspan="3" class="px-4 py-6 text-center text-slate-400">Belum ada kategori.</td>
          </tr>
          <tr v-for="c in categories" :key="c.id" class="border-t border-slate-100">
            <td class="px-4 py-3 font-medium">{{ c.name }}</td>
            <td class="px-4 py-3">{{ c.products_count }}</td>
            <td class="px-4 py-3 text-right">
              <button class="mr-3 text-indigo-600 hover:underline" @click="openEdit(c)">Ubah</button>
              <button class="text-red-600 hover:underline" @click="remove(c)">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <BaseModal :open="modalOpen" :title="editingId ? 'Ubah Kategori' : 'Tambah Kategori'" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1 block text-sm font-medium" for="category-name">Nama kategori</label>
          <input
            id="category-name"
            v-model="name"
            required
            maxlength="100"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="modalOpen = false">
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
  </div>
</template>