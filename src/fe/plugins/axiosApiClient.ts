import axios from 'axios';

export default defineNuxtPlugin((nuxtApp) => {
    const runctimeConfig = useRuntimeConfig()
    const axiosApiClient = axios.create()
    axiosApiClient.defaults.baseURL = runctimeConfig.public.apiUrl
    axiosApiClient.interceptors.request.use(function (config) {
        return config;
    });
    return {
        provide: {
            axiosApiClient: axiosApiClient,
        },
    };
});