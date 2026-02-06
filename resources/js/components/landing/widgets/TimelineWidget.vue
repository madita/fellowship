<template>
  <v-sheet class="py-16">
    <v-container>
      <div class="text-center mb-12">
        <h2 class="text-h3 text-md-h2 font-weight-bold mb-4">
          {{ content.title }}
        </h2>
        <p v-if="content.subtitle" class="text-h6 text-grey">
          {{ content.subtitle }}
        </p>
      </div>

      <v-row justify="center">
        <v-col cols="12" lg="10">
          <v-timeline
            :side="$vuetify.display.mdAndUp ? 'end' : undefined"
            :align="$vuetify.display.mdAndUp ? 'start' : 'start'"
            :truncate-line="$vuetify.display.mdAndUp ? undefined : 'both'"
          >
            <v-timeline-item
              v-for="(step, index) in content.steps"
              :key="index"
              :dot-color="step.color || 'primary'"
              size="large"
            >
              <template #icon>
                <v-icon v-if="step.icon" color="white">{{ step.icon }}</v-icon>
                <span v-else class="text-h6 font-weight-bold">{{ index + 1 }}</span>
              </template>

              <template #opposite>
                <div v-if="$vuetify.display.mdAndUp && step.date" class="text-h6 font-weight-bold">
                  {{ step.date }}
                </div>
              </template>

              <v-card elevation="2" class="timeline-card">
                <v-card-title class="d-flex align-center">
                  <div>
                    <div class="text-h5 font-weight-bold">{{ step.title }}</div>
                    <div v-if="!$vuetify.display.mdAndUp && step.date" class="text-subtitle-2 text-grey mt-1">
                      {{ step.date }}
                    </div>
                  </div>
                </v-card-title>

                <v-card-text>
                  <p class="text-body-1">{{ step.description }}</p>

                  <v-list v-if="step.details && step.details.length > 0" density="compact" class="mt-4">
                    <v-list-item
                      v-for="(detail, detailIndex) in step.details"
                      :key="detailIndex"
                      class="px-0"
                    >
                      <template #prepend>
                        <v-icon size="small" color="primary">mdi-check-circle</v-icon>
                      </template>
                      <v-list-item-title class="text-body-2">
                        {{ detail }}
                      </v-list-item-title>
                    </v-list-item>
                  </v-list>
                </v-card-text>
              </v-card>
            </v-timeline-item>
          </v-timeline>
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
      title: 'Our Process',
      subtitle: '',
      steps: []
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});
</script>

<style scoped>
.timeline-card {
  transition: transform 0.3s ease;
}

.timeline-card:hover {
  transform: translateX(8px);
}
</style>
