<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { errorMessage } from '@/lib/errors'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/BaseModal.vue'

const auth = useAuthStore()

const users = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const error = ref('')

const q = ref('')
const role = ref('')
const page = ref(1)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/users', {
      params: {
        q: q.value || undefined,
        role: role.value || undefined,
        page: page.value,
      },
    })

    users.value = data.data
    meta.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      total: data.total,
    }
  } catch (e) {
    error.value = errorMessage(e, 'Gagal memuat daftar pengguna.')
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

watch(role, () => {
  page.value = 1
  load()
})

function goToPage(n) {
  if (n < 1 || n > meta.value.last_page) return
  page.value = n
  load()
}

function isSelf(user) {
  return auth.user?.id === user.id
}

// ---- Form tambah / ubah ----
const formOpen = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'kasir',
  is_active: true,
})

function openCreate() {
  editingId.value = null
  Object.assign(form, {
    name: '',
    email: '',
    password: '',
    role: 'kasir',
    is_active: true,
  })
  formError.value = ''
  formOpen.value = true
}

function openEdit(user) {
  editingId.value = user.id
  Object.assign(form, {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role,
    is_active: !!user.is_active,
  })
  formError.value = ''
  formOpen.value = true
}

async function save() {
  saving.value = true
  formError.value = ''

  const payload = {
    name: form.name,
    email: form.email,
    role: form.role,
    is_active: form.is_active,
  }

  if (form.password) {
    payload.password = form.password
  }

  try {
    if (editingId.value) {
      await api.put(`/users/${editingId.value}`, payload)
    } else {
      await api.post('/users', payload)
    }

    formOpen.value = false
    await load()
  } catch (e) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function toggleActive(user) {
  const activating = !user.is_active

  const message = activating
    ? `Aktifkan kembali akun "${user.name}"?`
    : `Nonaktifkan akun "${user.name}"? Akun tidak bisa login dan semua sesi loginnya diakhiri.`

  if (!window.confirm(message)) return

  try {
    await api.put(`/users/${user.id}`, { is_active: activating })
    await load()
  } catch (e) {
    error.value = errorMessage(e, 'Gagal mengubah status akun.')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold">Pengguna</h1>
      <button
        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
        @click="openCreate"
      >
        Tambah Akun
      </button>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
      <input
        v-model="q"
        type="search"
        placeholder="Cari nama atau email..."
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 sm:w-64"
      />

      <select
        v-model="role"
        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500"
      >
        <option value="">Semua peran</option>
        <option value="admin">Admin</option>
        <option value="kasir">Kasir</option>
      </select>
    </div>

    <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Peran</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Memuat...</td>
          </tr>
          <tr v-else-if="users.length === 0">
            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Tidak ada pengguna.</td>
          </tr>

          <tr v-for="u in users" :key="u.id" class="border-t border-slate-100">
            <td class="px-4 py-3 font-medium">
              {{ u.name }}
              <span v-if="isSelf(u)" class="ml-1 text-xs text-slate-400">(Anda)</span>
            </td>
            <td class="px-4 py-3">{{ u.email }}</td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="u.role === 'admin' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
              >
                {{ u.role === 'admin' ? 'Admin' : 'Kasir' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="u.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'"
              >
                {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button class="mr-3 text-indigo-600 hover:underline" @click="openEdit(u)">Ubah</button>
              <button
                v-if="!isSelf(u)"
                class="hover:underline"
                :class="u.is_active ? 'text-red-600' : 'text-emerald-600'"
                @click="toggleActive(u)"
              >
                {{ u.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
      <span>{{ meta.total }} akun</span>
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

    <BaseModal :open="formOpen" :title="editingId ? 'Ubah Akun' : 'Tambah Akun'" @close="formOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1 block text-sm font-medium" for="u-name">Nama</label>
          <input
            id="u-name"
            v-model="form.name"
            required
            maxlength="100"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium" for="u-email">Email</label>
          <input
            id="u-email"
            v-model="form.email"
            type="email"
            required
            maxlength="150"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium" for="u-password">
            Kata sandi
            <span v-if="editingId" class="font-normal text-slate-400">(kosongkan bila tidak diubah)</span>
          </label>
          <input
            id="u-password"
            v-model="form.password"
            type="password"
            minlength="8"
            :required="!editingId"
            autocomplete="new-password"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          />
          <p class="mt-1 text-xs text-slate-400">Minimal 8 karakter.</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-sm font-medium" for="u-role">Peran</label>
            <select
              id="u-role"
              v-model="form.role"
              :disabled="editingId && editingId === auth.user?.id"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 disabled:bg-slate-100"
            >
              <option value="kasir">Kasir</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <label class="mt-6 flex items-center gap-2 text-sm">
            <input
              v-model="form.is_active"
              type="checkbox"
              :disabled="editingId && editingId === auth.user?.id"
              class="h-4 w-4 rounded border-slate-300"
            />
            Akun aktif
          </label>
        </div>

        <p v-if="editingId && form.password" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
          Mengganti kata sandi akan mengakhiri semua sesi login akun ini.
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
  </div>
</template>