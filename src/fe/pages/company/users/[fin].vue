<template>
  <v-card flat class="pa-4">
    <v-card-title class="d-flex align-center pe-2">
      <v-icon icon="mdi-account-edit"></v-icon>
      <span class="ml-2">Edit Company User</span>
      <v-spacer></v-spacer>
      <v-btn prepend-icon="mdi-keyboard-backspace" variant="outlined" color="primary" to="/company/users">Back</v-btn>
    </v-card-title>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-alert v-if="loadingData" type="info" variant="tonal" class="mb-4">Loading...</v-alert>

    <v-form v-if="!loadingData" ref="formRef" @submit.prevent="saveUser">
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
      <v-btn color="primary" type="submit" :loading="saving">Save</v-btn>
      <v-btn class="ml-2" variant="text" to="/company/users">Cancel</v-btn>
    </v-form>
  </v-card>
</template>

<script>
import { getCompanyUser, updateCompanyUser } from '~/services/companyUsersService'
import { USER_STATUS_OPTIONS } from '~/constants/userStatus'

export default {
  name: 'EditCompanyUser',
  data() {
    return {
      loadingData: true,
      saving: false,
      error: '',
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
  computed: {
    fin() {
      return this.$route.params.fin
    },
  },
  async mounted() {
    await this.loadUser()
  },
  methods: {
    async loadUser() {
      this.loadingData = true
      this.error = ''
      try {
        const response = await getCompanyUser(this.fin)
        const data = response.data?.data || {}
        this.form = {
          fin: data.fin || this.fin,
          name: data.name || '',
          surname: data.surname || '',
          father: data.father || '',
          dob: data.dob || '',
          email: data.email || '',
          status: data.status || 'new_register',
        }
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to load user'
      } finally {
        this.loadingData = false
      }
    },
    async saveUser() {
      const valid = await this.$refs.formRef?.validate?.()
      if (valid && valid.valid === false) return
      this.saving = true
      this.error = ''
      try {
        await updateCompanyUser(this.fin, { ...this.form, fin: (this.form.fin || '').toUpperCase() })
        await navigateTo('/company/users')
      } catch (e) {
        const data = e?.response?.data
        this.error = data?.message || 'Failed to update user'
        if (data?.errors) {
          this.error += ': ' + Object.values(data.errors).flat().join(' ')
        }
      } finally {
        this.saving = false
      }
    },
  },
}
</script>