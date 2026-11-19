import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  createCompanyUser,
  deleteCompanyUser,
  exportCompanyUsers,
  getCompanyUser,
  getCompanyUserAudit,
  getCompanyUsers,
  importCompanyUsers,
  updateCompanyUser,
} from '../../../services/companyUsersService'

/**
 * Phase 11 (first real frontend test in this repo). `useNuxtApp` is a
 * Nuxt auto-import, a real global at runtime inside a built Nuxt app but
 * not declared in this plain Vitest environment -- `vi.stubGlobal` makes
 * it one for the duration of each test, standing in for Nuxt's own
 * injection without pulling in the full Nuxt test runtime (see
 * vitest.config.ts's comment; same reasoning). This tests the one thing
 * that actually matters for this module: that each exported function
 * calls the right HTTP verb, the right URL, and forwards its arguments
 * correctly -- not axios itself (already a trusted, separately-tested
 * third-party library).
 */
describe('companyUsersService', () => {
  const axiosApiClient = {
    get: vi.fn().mockResolvedValue({ data: {} }),
    post: vi.fn().mockResolvedValue({ data: {} }),
    put: vi.fn().mockResolvedValue({ data: {} }),
    delete: vi.fn().mockResolvedValue({ data: {} }),
  }

  beforeEach(() => {
    vi.stubGlobal('useNuxtApp', () => ({ $axiosApiClient: axiosApiClient }))
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.clearAllMocks()
  })

  it('getCompanyUsers() GETs the list endpoint with the given params', () => {
    getCompanyUsers({ search: 'ali', page: 2 })
    expect(axiosApiClient.get).toHaveBeenCalledWith('/company/users', { params: { search: 'ali', page: 2 } })
  })

  it('getCompanyUsers() defaults to no params when called with none', () => {
    getCompanyUsers()
    expect(axiosApiClient.get).toHaveBeenCalledWith('/company/users', { params: {} })
  })

  it('getCompanyUser() GETs the single-record endpoint by fin', () => {
    getCompanyUser('AB12345')
    expect(axiosApiClient.get).toHaveBeenCalledWith('/company/users/AB12345')
  })

  it('createCompanyUser() POSTs the payload to the collection endpoint', () => {
    const payload = { fin: 'AB12345', name: 'Ali' }
    createCompanyUser(payload)
    expect(axiosApiClient.post).toHaveBeenCalledWith('/company/users', payload)
  })

  it('updateCompanyUser() PUTs the payload to the record endpoint by fin', () => {
    const payload = { name: 'Updated' }
    updateCompanyUser('AB12345', payload)
    expect(axiosApiClient.put).toHaveBeenCalledWith('/company/users/AB12345', payload)
  })

  it('deleteCompanyUser() DELETEs the record endpoint by fin', () => {
    deleteCompanyUser('AB12345')
    expect(axiosApiClient.delete).toHaveBeenCalledWith('/company/users/AB12345')
  })

  it('exportCompanyUsers() GETs the export endpoint as a blob, forwarding params', () => {
    exportCompanyUsers({ search: 'ali' })
    expect(axiosApiClient.get).toHaveBeenCalledWith('/company/users/export', {
      params: { search: 'ali' },
      responseType: 'blob',
    })
  })

  it('getCompanyUserAudit() GETs the audit endpoint by fin, forwarding params', () => {
    getCompanyUserAudit('AB12345', { per_page: 2 })
    expect(axiosApiClient.get).toHaveBeenCalledWith('/company/users/AB12345/audit', { params: { per_page: 2 } })
  })

  it('importCompanyUsers() POSTs a multipart form containing the file under the "file" key', () => {
    const file = new File(['fin,name\n'], 'import.csv', { type: 'text/csv' })
    importCompanyUsers(file)

    expect(axiosApiClient.post).toHaveBeenCalledTimes(1)
    const [url, formData, config] = axiosApiClient.post.mock.calls[0]
    expect(url).toBe('/company/users/import')
    expect(formData).toBeInstanceOf(FormData)
    expect(formData.get('file')).toBe(file)
    expect(config.headers['Content-Type']).toBe('multipart/form-data')
  })
})
