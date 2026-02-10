<template>
  <v-container class="py-8">
    <h2 v-if="content.title" class="text-h4 text-center mb-8">{{ content.title }}</h2>
    <v-row>
      <v-col
        v-for="(image, index) in content.images"
        :key="index"
        cols="6"
        md="4"
      >
        <v-img
          v-if="image.url"
          :src="image.url"
          :alt="image.caption"
          aspect-ratio="1"
          cover
          class="rounded"
        />
        <div v-else class="bg-grey-lighten-3 rounded pa-8 text-center" style="aspect-ratio: 1;">
          <v-icon icon="mdi-image" size="48" color="grey" />
        </div>
        <p v-if="image.caption" class="text-center text-caption mt-2">{{ image.caption }}</p>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Our Gallery',
      images: [
        { url: null, caption: 'Image 1' },
        { url: null, caption: 'Image 2' },
        { url: null, caption: 'Image 3' },
      ],
    }),
  },
  config: {
    type: Object,
    default: () => ({}),
  },
});
</script>
