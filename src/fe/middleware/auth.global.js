import { isAuthenticated } from '~/services/authService'

// Phase 9: client-side route guard. This is a UX convenience only -- the
// real enforcement is server-side (every /api/company/users/* route
// requires a valid Sanctum token and returns 401 otherwise, see
// plugins/axiosApiClient.ts's 401 handler). Without this guard an
// unauthenticated visitor would still land on a page that then fails
// every request it makes.
export default defineNuxtRouteMiddleware((to) => {
  if (!import.meta.client) {
    return
  }

  if (to.path === '/login') {
    return
  }

  if (!isAuthenticated()) {
    return navigateTo('/login')
  }
})
