import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('kasirku_token'))
  const user = ref(JSON.parse(localStorage.getItem('kasirku_user') || 'null'))

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  async function login(email, password) {
    const { data } = await api.post('/login', {
      email,
      password,
      device_name: 'web',
    })

    token.value = data.data.token
    user.value = data.data.user

    localStorage.setItem('kasirku_token', token.value)
    localStorage.setItem('kasirku_user', JSON.stringify(user.value))
  }

  function clear() {
    token.value = null
    user.value = null
    localStorage.removeItem('kasirku_token')
    localStorage.removeItem('kasirku_user')
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch {
      // token mungkin sudah tidak berlaku, tetap bersihkan sesi lokal
    }
    clear()
  }

  return { token, user, isLoggedIn, isAdmin, login, logout, clear }
})