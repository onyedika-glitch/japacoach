import axios, { AxiosError } from 'axios'
import { useUiStore } from '@/stores/ui'

export const api = axios.create({
  baseURL: '',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

let csrfReady: Promise<unknown> | null = null

/** Fetch the Sanctum CSRF cookie once per page load. */
export function ensureCsrf(force = false): Promise<unknown> {
  if (!csrfReady || force) {
    csrfReady = api.get('/sanctum/csrf-cookie').catch((e) => {
      csrfReady = null
      throw e
    })
  }
  return csrfReady
}

api.interceptors.request.use(async (config) => {
  const method = (config.method ?? 'get').toLowerCase()
  if (method !== 'get' && method !== 'head' && !config.url?.includes('/sanctum/csrf-cookie')) {
    await ensureCsrf()
  }
  return config
})

api.interceptors.response.use(
  (res) => res,
  async (error: AxiosError<{ code?: string; feature?: string; message?: string }>) => {
    const status = error.response?.status
    const url = error.config?.url ?? ''
    if (status === 401 && url !== '/api/me') {
      const { default: router } = await import('@/router')
      const current = router.currentRoute.value
      if (current.meta.requiresAuth) {
        router.push({ name: 'login', query: { redirect: current.fullPath } })
      }
    } else if (status === 402 && error.response?.data?.code === 'limit_reached') {
      useUiStore().showUpgrade(error.response.data.message, error.response.data.feature)
    } else if (status === 419 && error.config && !(error.config as { _retried?: boolean })._retried) {
      // CSRF token expired: refresh once and retry.
      ;(error.config as { _retried?: boolean })._retried = true
      await ensureCsrf(true)
      return api.request(error.config)
    }
    return Promise.reject(error)
  },
)

/** Extract a human-readable message from an API error. */
export function errorMessage(e: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (axios.isAxiosError(e)) {
    const data = e.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    if (data?.errors) {
      const first = Object.values(data.errors)[0]
      if (first?.[0]) return first[0]
    }
    if (data?.message) return data.message
    if (!e.response) return 'Network error. Check your connection and try again.'
  }
  return fallback
}

export function isLimitError(e: unknown): boolean {
  return axios.isAxiosError(e) && e.response?.status === 402
}
