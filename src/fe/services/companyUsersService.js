export const getCompanyUsers = (params = {}) => {
  return useNuxtApp().$axiosApiClient.get('/company/users', { params })
}

export const getCompanyUser = (fin) => {
  return useNuxtApp().$axiosApiClient.get(`/company/users/${fin}`)
}

export const createCompanyUser = (payload) => {
  return useNuxtApp().$axiosApiClient.post('/company/users', payload)
}

export const updateCompanyUser = (fin, payload) => {
  return useNuxtApp().$axiosApiClient.put(`/company/users/${fin}`, payload)
}

export const deleteCompanyUser = (fin) => {
  return useNuxtApp().$axiosApiClient.delete(`/company/users/${fin}`)
}
