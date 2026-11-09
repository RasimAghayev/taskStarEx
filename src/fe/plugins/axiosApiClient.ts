import axios from 'axios'

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const axiosApiClient = axios.create({
    baseURL: config.public.apiUrl || 'http://localhost/api',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  })

  axiosApiClient.interceptors.response.use(
    (response) => response,
    (error) => {
      if (error?.response?.data?.message) {
        console.error(error.response.data.message)
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
