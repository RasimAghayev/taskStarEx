export const getCompanyUsers = () => {
    return useNuxtApp().$axiosApiClient.get('/company/users')
}

export const getCompanyUser = (fin) => {
    return useNuxtApp().$axiosApiClient.get(`/company/users/${fin}`)
}

export const createCompanyUser = (companyusers) => {
    return useNuxtApp().$axiosApiClient.post('/company/users', companyusers)
}

export const updateCompanyUser = (fin, companyusers) => {
    return useNuxtApp().$axiosApiClient.patch(`/company/users/${fin}`, companyusers)
}

export const deleteCompanyUser = (fin) => {
    return useNuxtApp().$axiosApiClient.delete(`/company/users/${fin}`)
}