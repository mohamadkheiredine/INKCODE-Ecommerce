import axios from 'axios'
import { toApiError } from './api-error'

/** Empty means "same origin": the dev server and nginx proxy /api and /sanctum to Laravel. */
const apiOrigin = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

let requestLanguage = 'en'

/** Keeps API messages (validation, errors) in the interface language. */
export function setApiLanguage(language: string): void {
  requestLanguage = language
}

/** The one HTTP client for the versioned API: session cookie + CSRF, typed errors. */
export const http = axios.create({
  baseURL: `${apiOrigin}/api/v1`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

http.interceptors.request.use((config) => {
  config.headers.set('Accept-Language', requestLanguage)
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error: unknown) => Promise.reject(toApiError(error)),
)

/** Sanctum sets the XSRF-TOKEN cookie that every later state-changing request sends back. */
export async function ensureCsrfCookie(): Promise<void> {
  try {
    await axios.get(`${apiOrigin}/sanctum/csrf-cookie`, {
      withCredentials: true,
      withXSRFToken: true,
    })
  } catch (error) {
    throw toApiError(error)
  }
}
