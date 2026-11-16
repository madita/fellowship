<template>
  <v-sheet>
    <v-container class="py-6 pt-lg-15 text-center">
      <h1 class="text-h4 text-sm-h3 text-md-h2 text-lg-h1">
        {{ getProcessedTitle() }}
      </h1>
      <h2 class="text-h6 text-sm-h5 mt-4 w-full w-md-8-12 w-xl-half mx-auto">
        {{ content.subtitle }}
      </h2>
      <div class="mt-8">
        <v-btn
          v-if="content.primaryButton && content.primaryButton.text && content.primaryButton.link"
          :to="content.primaryButton.link"
          size="large"
          class="my-1 mx-sm-1 w-full w-sm-auto"
          color="primary"
        >
          {{ content.primaryButton.text }}
        </v-btn>
        <v-btn
          v-if="content.secondaryButton && content.secondaryButton.text && content.secondaryButton.link"
          :to="content.secondaryButton.link"
          size="large"
          class="my-1 mx-sm-1 w-full w-sm-auto"
        >
          {{ content.secondaryButton.text }}
        </v-btn>
      </div>
    </v-container>
  </v-sheet>
</template>

<script setup>
import { h } from 'vue';

const props = defineProps({
  content: {
    type: Object,
    required: false,
    default: () => ({
      title: 'Your awesome community',
      subtitle: 'Lorem ipsum dolor sit, amet consectetur adipisicing elit.',
      primaryButton: { text: 'Join Now!', link: '/auth/signup' },
      secondaryButton: { text: 'Learn more', link: '#' },
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});

// Process title to add primary class to words between asterisks
// e.g., "Your awesome *community*" becomes "Your awesome" + <span class="text-primary">community</span>
function getProcessedTitle() {
  const title = props.content.title || '';
  // Simple implementation - returns raw title. Can be enhanced for HTML rendering
  return title.replace(/\*/g, '');
}
</script>
