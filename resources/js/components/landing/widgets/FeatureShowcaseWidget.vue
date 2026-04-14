<template>
  <v-container class="py-12">
    <v-row :class="{ 'flex-row-reverse': content.imagePosition === 'left' }">
      <v-col cols="12" md="6" class="d-flex flex-column justify-center">
        <div v-if="content.subtitle" class="text-overline text-primary mb-2">
          {{ content.subtitle }}
        </div>
        <h2 class="text-h4 mb-4">{{ content.title }}</h2>
        <p class="text-body-1 text-grey">{{ content.description }}</p>
      </v-col>
      <v-col cols="12" md="6">
        <v-img
          v-if="content.image"
          :src="content.image"
          :alt="content.title"
          class="rounded"
          max-height="400"
        />
        <div v-else class="bg-grey-lighten-3 rounded pa-12 text-center">
          <v-icon icon="mdi-image" size="64" color="grey" />
        </div>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      subtitle: 'Work with us',
      title: 'Get your startup ready for business',
      description: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
      image: null,
      imagePosition: 'right',
    }),
  },
  config: {
    type: Object,
    default: () => ({}),
  },
});
</script>
