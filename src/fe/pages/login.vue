<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="4">
        <v-card>
          <v-card-title>StarEx CRUD APP — Sign in</v-card-title>
          <v-card-text>
            <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
            <v-form @submit.prevent="submit">
              <v-text-field
                v-model="email"
                label="Email"
                type="email"
                autocomplete="username"
                required
              />
              <v-text-field
                v-model="password"
                label="Password"
                type="password"
                autocomplete="current-password"
                required
              />
              <v-btn
                type="submit"
                color="primary"
                block
                :loading="submitting"
              >
                Sign in
              </v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import { login } from '~/services/authService'

export default {
  name: 'LoginPage',
  data() {
    return {
      email: '',
      password: '',
      error: '',
      submitting: false,
    }
  },
  methods: {
    async submit() {
      this.error = ''
      this.submitting = true
      try {
        await login(this.email, this.password)
        await this.$router.push('/company/users')
      } catch (e) {
        this.error = e?.response?.data?.message || 'Invalid credentials'
      } finally {
        this.submitting = false
      }
    },
  },
}
</script>
