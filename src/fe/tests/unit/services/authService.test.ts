import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AUTH_TOKEN_STORAGE_KEY, isAuthenticated, login, logout } from '../../../services/authService'

/**
 * Phase 11 (first real frontend test in this repo). Same `useNuxtApp`
 * stubbing rationale as companyUsersService.test.ts. `import.meta.client`
 * is replaced at build time by vitest.config.ts's `define` (see that
 * file's comment) so this module's client-only branches run the same way
 * under test as in a real browser bundle.
 */
describe('authService', () => {
  const axiosApiClient = {
    post: vi.fn(),
  }

  beforeEach(() => {
    vi.stubGlobal('useNuxtApp', () => ({ $axiosApiClient: axiosApiClient }))
    localStorage.clear()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.clearAllMocks()
  })

  describe('login()', () => {
    it('stores the returned token and returns the response data on success', async () => {
      axiosApiClient.post.mockResolvedValue({
        data: { data: { token: 'abc123', token_type: 'Bearer', user: { email: 'a@example.com' } } },
      })

      const result = await login('a@example.com', 'password')

      expect(axiosApiClient.post).toHaveBeenCalledWith('/login', { email: 'a@example.com', password: 'password' })
      expect(localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)).toBe('abc123')
      expect(result).toEqual({ token: 'abc123', token_type: 'Bearer', user: { email: 'a@example.com' } })
    })

    it('does not store anything if the response carries no token', async () => {
      axiosApiClient.post.mockResolvedValue({ data: { data: { user: { email: 'a@example.com' } } } })

      await login('a@example.com', 'password')

      expect(localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)).toBeNull()
    })

    it('propagates a rejected request (e.g. 401 on wrong credentials) without storing a token', async () => {
      axiosApiClient.post.mockRejectedValue(new Error('401'))

      await expect(login('a@example.com', 'wrong')).rejects.toThrow('401')
      expect(localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)).toBeNull()
    })
  })

  describe('logout()', () => {
    it('removes the stored token even when the server call succeeds', async () => {
      localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, 'abc123')
      axiosApiClient.post.mockResolvedValue({ data: {} })

      await logout()

      expect(axiosApiClient.post).toHaveBeenCalledWith('/logout')
      expect(localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)).toBeNull()
    })

    /**
     * The real defect class this guards against: a logout request that
     * fails over the network (not a 401 -- an unreachable server) must
     * not leave a dead token sitting in localStorage that every
     * subsequent request keeps retrying with. authService.js's own
     * `finally` block is what's being verified here.
     */
    it('still removes the stored token when the server call itself fails', async () => {
      localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, 'abc123')
      axiosApiClient.post.mockRejectedValue(new Error('network error'))

      await expect(logout()).rejects.toThrow('network error')
      expect(localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)).toBeNull()
    })
  })

  describe('isAuthenticated()', () => {
    it('returns true when a token is stored', () => {
      localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, 'abc123')
      expect(isAuthenticated()).toBe(true)
    })

    it('returns false when no token is stored', () => {
      expect(isAuthenticated()).toBe(false)
    })
  })
})
