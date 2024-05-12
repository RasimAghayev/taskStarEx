<template>
  <v-app id="inspire">
    <v-navigation-drawer v-model="drawer">
      <v-sheet
          class="pa-4"
          color="grey-lighten-4"
      >
        <div>StarEx CRUD APP</div>
      </v-sheet>
      <v-divider></v-divider>
      <v-list>
        <v-list-item
            v-for="[icon, text,link] in links"
            :key="icon"
            :prepend-icon="icon"
            :title="text"
            :to="link"
            link
        ></v-list-item>
      </v-list>
    </v-navigation-drawer>


    <v-main>
      <router-view/>
    </v-main>
  </v-app>
</template>
<script>
export default {
  data: () => ({
    drawer: null,
    links: [
      ['mdi-domain', 'Company', '/company/users'],
    ],
  }),
}
</script>