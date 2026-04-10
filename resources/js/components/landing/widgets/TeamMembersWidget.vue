<template>
  <v-sheet class="py-16 bg-grey-lighten-4">
    <v-container>
      <div class="text-center mb-12">
        <h2 class="text-h3 text-md-h2 font-weight-bold mb-4">
          {{ content.title }}
        </h2>
        <p v-if="content.subtitle" class="text-h6 text-grey">
          {{ content.subtitle }}
        </p>
      </div>

      <v-row>
        <v-col
          v-for="(member, index) in content.members"
          :key="index"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <v-card class="team-member-card text-center" elevation="2" hover>
            <v-card-text class="pa-6">
              <v-avatar
                :image="member.image || undefined"
                :color="member.image ? undefined : 'grey'"
                size="120"
                class="mb-4"
              >
                <v-icon v-if="!member.image" size="64" color="white">mdi-account</v-icon>
              </v-avatar>

              <h3 class="text-h5 font-weight-bold mb-1">
                {{ member.name }}
              </h3>

              <p class="text-subtitle-1 text-primary mb-3">
                {{ member.role }}
              </p>

              <p v-if="member.bio" class="text-body-2 text-grey-darken-1 mb-4">
                {{ member.bio }}
              </p>

              <div class="d-flex justify-center gap-2">
                <v-btn
                  v-if="member.email"
                  icon
                  size="small"
                  variant="text"
                  :href="`mailto:${member.email}`"
                  target="_blank"
                >
                  <v-icon>mdi-email</v-icon>
                </v-btn>

                <v-btn
                  v-if="member.linkedin"
                  icon
                  size="small"
                  variant="text"
                  :href="member.linkedin"
                  target="_blank"
                >
                  <v-icon>mdi-linkedin</v-icon>
                </v-btn>

                <v-btn
                  v-if="member.twitter"
                  icon
                  size="small"
                  variant="text"
                  :href="`https://twitter.com/${member.twitter}`"
                  target="_blank"
                >
                  <v-icon>mdi-twitter</v-icon>
                </v-btn>

                <v-btn
                  v-if="member.github"
                  icon
                  size="small"
                  variant="text"
                  :href="`https://github.com/${member.github}`"
                  target="_blank"
                >
                  <v-icon>mdi-github</v-icon>
                </v-btn>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </v-sheet>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Meet Our Team',
      subtitle: '',
      members: []
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});
</script>

<style scoped>
.team-member-card {
  height: 100%;
  transition: transform 0.3s ease;
}

.team-member-card:hover {
  transform: translateY(-8px);
}

.gap-2 {
  gap: 8px;
}
</style>
