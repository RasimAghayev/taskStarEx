<template>

    <v-card flat>
      <v-card-title class="d-flex align-center pe-2">
        <v-icon icon="mdi-account"></v-icon> &nbsp;
        Company User list
        <v-spacer></v-spacer>
        <v-btn prepend-icon="mdi-account-plus"
               variant="outlined"
               color="blue"
               href="/company/users/create"
        >
          Button
        </v-btn>
        <v-spacer></v-spacer>
        <v-text-field
            v-model="search"
            density="compact"
            label="Search"
            prepend-inner-icon="mdi-magnify"
            variant="solo-filled"
            flat
            hide-details
            single-line
        ></v-text-field>
      </v-card-title>

      <v-divider></v-divider>
      <v-data-table v-model:search="search" :items="company_users">
        <template slot="company_user.delete" slot-scope="props">
          <v-btn class="mx-2" icon @click="() => delete(props.item)">
            <v-icon dark>mdi-delete</v-icon>
          </v-btn>
        </template>
      </v-data-table>
    </v-card>
</template>

<script>
import  {getCompanyUsers,deleteCompanyUser} from "~/services/companyUsersService.js";
export default {
  name: "company-users",
  data () {
    return {
      company_users:[],
      search: '',
    }
  },
  methods: {
    getDisplayCompanyUsers(companyUser) {
      return {
        fin: companyUser.fin,
        name: companyUser.name,
        surname: companyUser.surname,
        father: companyUser.father,
        dob: companyUser.dob,
        email: companyUser.email,
        status: companyUser.status,
      };
    },
    retrieveCompanyUsers() {
      getCompanyUsers()
          .then((response) => {
            this.company_users = response.data.data.map(this.getDisplayCompanyUsers);
            console.log(response);
          })
          .catch((e) => {
            console.log(e);
          });
    },
    deleteTutorial(fin) {
      deleteCompanyUser(fin)
          .then(() => {
            this.refreshList();
          })
          .catch((e) => {
            console.log(e);
          });
    },

    refreshList() {
      this.retrieveCompanyUsers();
    },
  },
  mounted() {
    this.retrieveCompanyUsers();
  },
}
</script>

<style>
.list {
  max-width: 750px;
}
</style>