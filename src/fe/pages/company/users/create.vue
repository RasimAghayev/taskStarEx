<template>
  <v-card flat>
    <v-card-title class="d-flex align-center pe-2">
      <v-icon icon="mdi-account"></v-icon> &nbsp;
      Company User list
      <v-spacer></v-spacer>
      <v-btn prepend-icon="mdi-keyboard-backspace"
             variant="outlined"
             color="blue"
             href="/company/users"
      >
        Back
      </v-btn>
    </v-card-title>

    <v-divider></v-divider>
    <div class="submit-form">
      <p class="headline">Add Company User</p>

      <div v-if="!submitted">
        <v-form ref="form" lazy-validation>
          <v-text-field
              v-model="company_user.fin"
              :rules="[(v) => !!v || 'FIN is required']"
              label="FIN"
              required
          ></v-text-field>

          <v-text-field
              v-model="company_user.name"
              :rules="[(v) => !!v || 'Name is required']"
              label="Name"
              required
          ></v-text-field>

          <v-text-field
              v-model="company_user.surname"
              :rules="[(v) => !!v || 'Surname is required']"
              label="Surname"
              required
          ></v-text-field>
          <v-text-field
              v-model="company_user.father"
              :rules="[(v) => !!v || 'Father name is required']"
              label="Father name"
              required
          ></v-text-field>
            <date-picker
                  v-model="company_user.dob"
                  :rules="[(v) => !!v || 'Date of birthday is required']"
                  label="Date of birthday" class="mt-3"
                  required
                  >
            </date-picker>
          <v-text-field
              v-model="company_user.email"
              :rules="[(v) => !!v || 'Email is required']"
              label="Email"
              type="email"
              required
          ></v-text-field>
        </v-form>

        <v-btn color="primary" class="mt-3" @click="saveTutorial">Submit</v-btn>
      </div>

      <div v-else>
        <v-card class="mx-auto">
          <v-card-title>
            Submitted successfully!
          </v-card-title>

          <v-card-subtitle>
            Click the button to add new Tutorial.
          </v-card-subtitle>

          <v-card-actions>
            <v-btn color="success" @click="newTutorial">Add</v-btn>
          </v-card-actions>
        </v-card>
      </div>
    </div>
  </v-card>
</template>

<script>
import {createCompanyUser} from "~/services/companyUsersService";

export default {
  name: "add-tutorial",
  data() {
    return {
      company_user: {
        id: null,
        title: "",
        description: "",
        published: false,
      },
      submitted: false,
    };
  },
  methods: {
    saveTutorial() {
      var data = {
        title: this.company_user.title,
        description: this.company_user.description,
      };

      createCompanyUser(data)
          .then((response) => {
            this.company_user.fin = response.data.data.fin;
            console.log(response.data.data);
            this.submitted = true;
          })
          .catch((e) => {
            console.log(e);
          });
    },

    newTutorial() {
      this.submitted = false;
      this.tutorial = {};
    },
  },
};
</script>

<style>
.submit-form {
  max-width: 500px;
}
</style>