// Phase 9: token-based (Sanctum) authentication. The token is stored in
// localStorage only -- there is no server-side session for this SPA -- and
// read back by plugins/axiosApiClient.ts's request interceptor.
export const AUTH_TOKEN_STORAGE_KEY = 'taskStarEx.authToken'

export const login = async (email, password) => {
  const response = await useNuxtApp().$axiosApiClient.post('/login', { email, password })
  const token = response.data?.data?.token

  if (token && import.meta.client) {
    localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, token)
  }

  return response.data?.data
}

export const logout = async () => {
  try {
    await useNuxtApp().$axiosApiClient.post('/logout')
  } finally {
    if (import.meta.client) {
      localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY)
    }
  }
}

export const isAuthenticated = () => {
  if (!import.meta.client) {
    return false
  }

  return !!localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)
}
