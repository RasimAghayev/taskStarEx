<template>
  <v-card flat class="pa-4">
    <v-card-title class="d-flex align-center pe-2">
      <v-icon icon="mdi-account-plus" />
      <span class="ml-2">Add Company User</span>
      <v-spacer />
      <v-btn prepend-icon="mdi-keyboard-backspace" variant="outlined" color="primary" to="/company/users">Back</v-btn>
    </v-card-title>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-4">User created successfully.</v-alert>

    <v-form ref="formRef" @submit.prevent="saveUser">
      <v-row>
        <v-col cols="12" md="6"><v-text-field v-model="form.fin" label="FIN" maxlength="7" :rules="[req]" /></v-col>
        <v-col cols="12" md="6"><v-text-field v-model="form.name" label="Name" :rules="[req]" /></v-col>
        <v-col cols="12" md="6"><v-text-field v-model="form.surname" label="Surname" :rules="[req]" /></v-col>
        <v-col cols="12" md="6"><v-text-field v-model="form.father" label="Father name" :rules="[req]" /></v-col>
        <v-col cols="12" md="6">
          <v-text-field v-model="form.dob" label="Date of birth (YYYY-MM-DD)" type="date" :rules="[req]" />
        </v-col>
        <v-col cols="12" md="6"><v-text-field v-model="form.email" label="Email" type="email" :rules="[req]" /></v-col>
        <v-col cols="12" md="6">
          <v-select v-model="form.status" :items="statusItems" item-title="title" item-value="value" label="Status" :rules="[req]" />
        </v-col>
      </v-row>
      <v-btn color="primary" type="submit" :loading="loading">Save</v-btn>
    </v-form>
  </v-card>
</template>

<script>
import { createCompanyUser } from '~/services/companyUsersService'
import { USER_STATUS_OPTIONS } from '~/constants/userStatus'

export default {
  name: 'AddCompanyUser',
  data() {
    return {
      loading: false,
      error: '',
      success: false,
      form: {
        fin: '',
        name: '',
        surname: '',
        father: '',
        dob: '',
        email: '',
        status: 'new_register',
      },
      statusItems: USER_STATUS_OPTIONS,
      req: (v) => !!v || 'Required',
    }
  },
  methods: {
    async saveUser() {
      const valid = await this.$refs.formRef?.validate?.()
      if (valid && valid.valid === false) return
      this.loading = true
      this.error = ''
      this.success = false
      try {
        await createCompanyUser({ ...this.form, fin: (this.form.fin || '').toUpperCase() })
        this.success = true
        await navigateTo('/company/users')
      } catch (e) {
        const data = e?.response?.data
        this.error = data?.message || 'Failed to create user'
        if (data?.errors) {
          this.error += ': ' + Object.values(data.errors).flat().join(' ')
        }
      } finally {
        this.loading = false
      }
    },
  },
}
</script>
