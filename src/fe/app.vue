<template>
  <v-app id="inspire">
    <v-navigation-drawer v-if="!isLoginPage" v-model="drawer">
      <v-sheet
          class="pa-4"
          color="grey-lighten-4"
      >
        <div>StarEx CRUD APP</div>
      </v-sheet>
      <v-divider />
      <v-list>
        <v-list-item
            v-for="[icon, text,link] in links"
            :key="icon"
            :prepend-icon="icon"
            :title="text"
            :to="link"
            link
        />
      </v-list>
      <template #append>
        <v-divider />
        <v-list>
          <v-list-item prepend-icon="mdi-logout" title="Sign out" link @click="signOut" />
        </v-list>
      </template>
    </v-navigation-drawer>


    <v-main>
      <router-view/>
    </v-main>
  </v-app>
</template>
<script>
// Phase 9: minimal auth wiring so this SPA stays usable once every
// /api/company/users/* route requires a Sanctum token (routes/api.php).
// Deliberately small -- one login page, a global route middleware
// (middleware/auth.global.js), and this one sign-out action -- no
// separate "account menu" component or role UI this app's single-actor
// model doesn't need (KISS/YAGNI).
import { logout } from '~/services/authService'

export default {
  data: () => ({
    drawer: null,
    links: [
      ['mdi-domain', 'Company', '/company/users'],
    ],
  }),
  computed: {
    isLoginPage() {
      return this.$route.path === '/login'
    },
  },
  methods: {
    async signOut() {
      await logout()
      await this.$router.push('/login')
    },
  },
}
</script>