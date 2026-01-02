<template>
  <v-sheet>
    <v-container class="py-8">
      <div class="d-flex flex-column flex-lg-row justify-space-between align-center">
        <div class="text-center text-lg-left">
          <div class="text-h3">{{ content.title || 'Ready to talk?' }}</div>
          <div class="text-h3 text-primary">{{ content.description || 'Our team is here to help.' }}</div>
        </div>
        <div class="mt-4 mt-lg-0">
          <v-btn
            size="large"
            class="my-1 mx-sm-2 w-full w-sm-auto"
            color="primary"
            :to="content.buttonLink || '#'"
          >
            {{ content.buttonText || 'Contact Sales' }}
          </v-btn>
        </div>
      </div>
    </v-container>
  </v-sheet>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    required: false,
    default: () => ({
      title: 'Ready to talk?',
      description: 'Our team is here to help.',
      buttonText: 'Contact Sales',
      buttonLink: '/contact'
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});

const { content } = props;
</script>
