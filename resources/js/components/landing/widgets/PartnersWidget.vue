<template>
  <v-container v-if="content.showContent" class="py-8 text-center">
    <p v-if="content.message" class="text-body-1 text-grey">
      {{ content.message }}
    </p>
  </v-container>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      showContent: false,
      message: '',
    }),
  },
  config: {
    type: Object,
    default: () => ({}),
  },
});
</script>
