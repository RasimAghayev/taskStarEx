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

    <v-divider class="my-6"></v-divider>

    <div class="d-flex align-center mb-2">
      <v-icon icon="mdi-history" size="small"></v-icon>
      <span class="ml-2 text-subtitle-1">History</span>
    </div>
    <v-alert v-if="auditError" type="error" variant="tonal" density="compact" class="mb-2">{{ auditError }}</v-alert>
    <v-list v-else lines="two" density="compact">
      <v-list-item v-if="loadingAudit">Loading history...</v-list-item>
      <v-list-item v-else-if="auditEntries.length === 0">No recorded changes yet.</v-list-item>
      <v-list-item v-for="entry in auditEntries" :key="entry.id">
        <v-list-item-title>
          <v-chip size="x-small" class="mr-2" :color="actionColor(entry.action)">{{ entry.action }}</v-chip>
          {{ entry.created_at }}
        </v-list-item-title>
        <v-list-item-subtitle>{{ changedFieldsSummary(entry) }}</v-list-item-subtitle>
      </v-list-item>
    </v-list>
  </v-card>
</template>

<script>
import { getCompanyUser, getCompanyUserAudit, updateCompanyUser } from '~/services/companyUsersService'
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
      loadingAudit: true,
      auditEntries: [],
      auditError: '',
    }
  },
  computed: {
    fin() {
      return this.$route.params.fin
    },
  },
  async mounted() {
    await this.loadUser()
    await this.loadAudit()
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
    async loadAudit() {
      this.loadingAudit = true
      this.auditError = ''
      try {
        const response = await getCompanyUserAudit(this.fin)
        this.auditEntries = response.data?.data || []
      } catch (e) {
        this.auditError = e?.response?.data?.message || 'Failed to load history'
      } finally {
        this.loadingAudit = false
      }
    },
    actionColor(action) {
      return { created: 'success', updated: 'info', deleted: 'error' }[action] || 'blue-grey'
    },
    changedFieldsSummary(entry) {
      if (entry.action === 'updated') {
        return Object.entries(entry.changes || {})
          .map(([field, diff]) => `${field}: ${diff.old} -> ${diff.new}`)
          .join(', ')
      }

      return Object.entries(entry.changes || {})
        .map(([field, value]) => `${field}: ${value}`)
        .join(', ')
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