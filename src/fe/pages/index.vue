<template>
  <v-card flat>
    <v-card-title class="d-flex align-center pe-2">
      <v-icon icon="mdi-account"></v-icon>
      <span class="ml-2">Company Users</span>
      <v-spacer></v-spacer>
      <v-btn prepend-icon="mdi-account-plus" variant="outlined" color="primary" to="/company/users/create">
        Add New User
      </v-btn>
    </v-card-title>
    <v-divider></v-divider>
    <v-card-text>Use the Company Users page to manage employees (CRUD, search, pagination).</v-card-text>
  </v-card>
</template>
