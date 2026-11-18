<template>
  <v-card flat>
    <v-card-title class="d-flex align-center pe-2 ga-2">
      <v-icon icon="mdi-account-group"></v-icon>
      <span>Company User List</span>
      <v-spacer></v-spacer>
      <v-btn
        prepend-icon="mdi-download"
        variant="outlined"
        color="secondary"
        class="mr-2"
        :loading="exporting"
        @click="exportUsers"
      >
        Export CSV
      </v-btn>
      <v-btn prepend-icon="mdi-account-plus" variant="outlined" color="primary" to="/company/users/create">
        Add New User
      </v-btn>
    </v-card-title>

    <v-card-text>
      <v-text-field
        v-model="search"
        density="compact"
        label="Search users"
        prepend-inner-icon="mdi-magnify"
        variant="solo-filled"
        flat
        hide-details
        clearable
        @update:model-value="onSearch"
      />
    </v-card-text>

    <v-alert v-if="error" type="error" variant="tonal" class="mx-4 mb-2">{{ error }}</v-alert>

    <v-data-table-server
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="companyUsers"
      :items-length="totalItems"
      :loading="loading"
      item-value="fin"
      @update:options="loadItems"
    >
      <template #item.status_label="{ item }">
        <v-chip size="small" color="blue-grey">{{ item.status_label || item.status }}</v-chip>
      </template>
      <template #item.actions="{ item }">
        <v-btn icon="mdi-pencil" variant="text" color="primary" :to="`/company/users/${item.fin}`" />
        <v-btn icon="mdi-delete" variant="text" color="error" @click="removeUser(item.fin)" />
      </template>
    </v-data-table-server>
  </v-card>
</template>

<script>
import { deleteCompanyUser, exportCompanyUsers, getCompanyUsers } from '~/services/companyUsersService'

export default {
  name: 'CompanyUsersList',
  data() {
    return {
      companyUsers: [],
      search: '',
      loading: false,
      error: '',
      page: 1,
      itemsPerPage: 10,
      totalItems: 0,
      exporting: false,
      headers: [
        { title: 'FIN', key: 'fin' },
        { title: 'Name', key: 'name' },
        { title: 'Surname', key: 'surname' },
        { title: 'Father', key: 'father' },
        { title: 'DOB', key: 'dob_display' },
        { title: 'Email', key: 'email' },
        { title: 'Status', key: 'status_label' },
        { title: 'Actions', key: 'actions', sortable: false },
      ],
    }
  },
  mounted() {
    this.loadItems()
  },
  methods: {
    async loadItems() {
      this.loading = true
      this.error = ''
      try {
        const response = await getCompanyUsers({
          search: this.search || undefined,
          per_page: this.itemsPerPage,
          page: this.page,
        })
        const payload = response.data || {}
        this.companyUsers = payload.data || []
        this.totalItems = payload.meta?.total ?? this.companyUsers.length
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to load users'
      } finally {
        this.loading = false
      }
    },
    onSearch() {
      this.page = 1
      this.loadItems()
    },
    async removeUser(fin) {
      if (!confirm(`Delete user ${fin}?`)) return
      this.error = ''
      try {
        await deleteCompanyUser(fin)
        await this.loadItems()
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to delete user'
      }
    },
    async exportUsers() {
      this.exporting = true
      this.error = ''
      try {
        const response = await exportCompanyUsers({ search: this.search || undefined })
        const url = URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }))
        const link = document.createElement('a')
        link.href = url
        link.download = `company-users-${Date.now()}.csv`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        URL.revokeObjectURL(url)
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to export users'
      } finally {
        this.exporting = false
      }
    },
  },
}
</script>
