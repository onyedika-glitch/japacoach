import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, ensureCsrf } from '@/lib/api'
import type { User } from '@/lib/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const booted = ref(false)
  let bootPromise: Promise<void> | null = null

  const isLoggedIn = computed(() => user.value !== null)
  const isAdmin = computed(() => !!user.value?.is_admin)

  async function fetchMe(): Promise<void> {
    try {
      const { data } = await api.get<{ user: User }>('/api/me')
      user.value = data.user
    } catch {
      user.value = null
    } finally {
      booted.value = true
    }
  }

  function boot(): Promise<void> {
    if (!bootPromise) bootPromise = fetchMe()
    return bootPromise
  }

  async function login(payload: { email: string; password: string; remember?: boolean }) {
    await ensureCsrf()
    const { data } = await api.post<{ user: User }>('/api/auth/login', payload)
    user.value = data.user
  }

  async function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
    referral_code?: string
  }) {
    await ensureCsrf()
    const { data } = await api.post<{ user: User }>('/api/auth/register', payload)
    user.value = data.user
  }

  async function logout() {
    try {
      await api.post('/api/auth/logout')
    } finally {
      user.value = null
    }
  }

  async function update(payload: Partial<Pick<User, 'name' | 'test_type' | 'target_band' | 'test_date' | 'email_reminders'>>) {
    const { data } = await api.patch<{ user: User }>('/api/me', payload)
    user.value = data.user
  }

  async function deleteAccount() {
    await api.delete('/api/me', { data: { confirm: 'DELETE' } })
    user.value = null
  }

  function setUser(u: User) {
    user.value = u
  }

  return { user, booted, isLoggedIn, isAdmin, boot, fetchMe, login, register, logout, update, deleteAccount, setUser }
})
