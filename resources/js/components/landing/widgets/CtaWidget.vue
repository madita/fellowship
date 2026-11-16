<template>
  <v-sheet color="primary" class="py-12">
    <v-container class="text-center">
      <h2 class="text-h4 text-white mb-4">{{ content.title }}</h2>
      <p v-if="content.description" class="text-body-1 text-white mb-6">
        {{ content.description }}
      </p>
      <v-btn
        v-if="content.buttonText && content.buttonLink"
        :to="content.buttonLink"
        color="white"
        size="large"
        variant="elevated"
      >
        {{ content.buttonText }}
      </v-btn>
    </v-container>
  </v-sheet>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Ready to talk? Our team is here to help',
      description: 'Get in touch with us today.',
      buttonText: 'Contact Us',
      buttonLink: '/contact',
    }),
  },
  config: {
    type: Object,
    default: () => ({}),
  },
});
</script>
