<template>
    <div class="partners-widget">
        <!-- Partners content can be added here in the future -->
        <!-- The SVG decoration is now handled at the section level -->
        <div v-if="content?.showContent" class="pa-8 text-center">
            <p class="text-body-1">{{ content?.message || 'Partner section' }}</p>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      showContent: false,
      message: ''
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});
</script>

<style scoped>
.partners-widget {
    min-height: 100px;
}
</style>
