import axios from 'axios'
import { AUTH_TOKEN_STORAGE_KEY } from '~/services/authService'

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const axiosApiClient = axios.create({
    baseURL: config.public.apiUrl || 'http://localhost/api',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  })

  // Phase 9: /api/company/users/* now requires a Sanctum bearer token.
  // The token lives only in the browser (localStorage) -- this plugin
  // runs on both server and client renders, so every access is guarded;
  // on the server there is no browser-stored token anyway (SSR requests
  // are unauthenticated here, same as before this phase, since this app
  // has no server-side session).
  axiosApiClient.interceptors.request.use((requestConfig) => {
    if (import.meta.client) {
      const token = localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)
      if (token) {
        requestConfig.headers.Authorization = `Bearer ${token}`
      }
    }
    return requestConfig
  })

  axiosApiClient.interceptors.response.use(
    (response) => response,
    (error) => {
      if (error?.response?.data?.message) {
        console.error(error.response.data.message)
      }

      // A 401 means the stored token is missing/invalid/expired -- drop
      // it and send the user back to /login rather than leaving them on
      // a page that will keep failing every request.
      if (error?.response?.status === 401 && import.meta.client) {
        localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY)
        const currentPath = window.location.pathname
        if (currentPath !== '/login') {
          navigateTo('/login')
        }
      }

      return Promise.reject(error)
    }
  )

  return {
    provide: {
      axiosApiClient,
    },
  }
})
