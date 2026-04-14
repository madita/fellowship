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

      <v-row align="center" justify="center">
        <v-col
          v-for="(logo, index) in content.logos"
          :key="index"
          cols="6"
          sm="4"
          md="3"
          lg="2"
          class="d-flex justify-center"
        >
          <div class="client-logo-wrapper">
            <a
              v-if="logo.link"
              :href="logo.link"
              target="_blank"
              class="client-logo-link"
            >
              <img
                v-if="logo.image"
                :src="logo.image"
                :alt="logo.name"
                class="client-logo"
              />
              <div v-else class="client-logo-placeholder">
                <v-icon size="48" color="grey">mdi-domain</v-icon>
                <p class="text-caption text-grey mt-2">{{ logo.name }}</p>
              </div>
            </a>

            <div v-else>
              <img
                v-if="logo.image"
                :src="logo.image"
                :alt="logo.name"
                class="client-logo"
              />
              <div v-else class="client-logo-placeholder">
                <v-icon size="48" color="grey">mdi-domain</v-icon>
                <p class="text-caption text-grey mt-2">{{ logo.name }}</p>
              </div>
            </div>
          </div>
        </v-col>
      </v-row>

      <div v-if="content.showCta" class="text-center mt-12">
        <v-btn
          color="primary"
          size="large"
          :href="content.ctaLink || '#'"
        >
          {{ content.ctaText || 'Become a Partner' }}
        </v-btn>
      </div>
    </v-container>
  </v-sheet>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Trusted By Leading Companies',
      subtitle: '',
      logos: [],
      showCta: false,
      ctaText: 'Become a Partner',
      ctaLink: '#'
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});
</script>

<style scoped>
.client-logo-wrapper {
  width: 100%;
  max-width: 160px;
  padding: 16px;
}

.client-logo {
  width: 100%;
  height: auto;
  max-height: 80px;
  object-fit: contain;
  filter: grayscale(100%);
  opacity: 0.6;
  transition: all 0.3s ease;
}

.client-logo:hover {
  filter: grayscale(0%);
  opacity: 1;
  transform: scale(1.05);
}

.client-logo-link {
  text-decoration: none;
  display: block;
}

.client-logo-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 80px;
  padding: 16px;
  background: rgba(0, 0, 0, 0.02);
  border-radius: 8px;
  transition: all 0.3s ease;
}

.client-logo-placeholder:hover {
  background: rgba(0, 0, 0, 0.05);
  transform: scale(1.05);
}
</style>
