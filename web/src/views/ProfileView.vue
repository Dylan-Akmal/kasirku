<script setup>
import { ref } from 'vue'
import api from '@/lib/api'
import { errorMessage } from '@/lib/errors'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const currentPassword = ref('')
const password = ref('')
const confirmation = ref('')

const saving = ref(false)
const error = ref('')
const success = ref('')

async function submit() {
  error.value = ''
  success.value = ''

  if (password.value !== confirmation.value) {
    error.value = 'Konfirmasi kata sandi tidak sama.'
    return
  }

  saving.value = true

  try {
    await api.put('/password', {
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: confirmation.value,
    })

    success.value = 'Kata sandi berhasil diubah. Sesi login di perangkat lain diakhiri.'
    currentPassword.value = ''
    password.value = ''
    confirmation.value = ''
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-md">
    <h1 class="text-xl font-bold">Akun Saya</h1>

    <div class="mt-4 rounded-xl bg-white p-5 ring-1 ring-slate-200">
      <p class="text-lg font-semibold">{{ auth.user?.name }}</p>
      <p class="text-sm text-slate-500">{{ auth.user?.email }}</p>
      <span
        class="mt-2 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
        :class="auth.isAdmin ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
      >
        {{ auth.isAdmin ? 'Admin' : 'Kasir' }}
      </span>
    </div>

    <form class="mt-5 space-y-4 rounded-xl bg-white p-5 ring-1 ring-slate-200" @submit.prevent="submit">
      <h2 class="font-semibold">Ubah kata sandi</h2>

      <div>
        <label class="mb-1 block text-sm font-medium" for="current">Kata sandi saat ini</label>
        <input
          id="current"
          v-model="currentPassword"
          type="password"
          required
          autocomplete="current-password"
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
        />
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium" for="new">Kata sandi baru</label>
        <input
          id="new"
          v-model="password"
          type="password"
          required
          minlength="8"
          autocomplete="new-password"
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
        />
        <p class="mt-1 text-xs text-slate-400">Minimal 8 karakter.</p>
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium" for="confirm">Ulangi kata sandi baru</label>
        <input
          id="confirm"
          v-model="confirmation"
          type="password"
          required
          autocomplete="new-password"
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
        {{ saving ? 'Menyimpan...' : 'Ubah kata sandi' }}
      </button>
    </form>
  </div>
</template>