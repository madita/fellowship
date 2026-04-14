<template>
  <v-sheet
    class="py-16"
    :class="content.containerClass || ''"
    :style="content.containerStyle || ''"
  >
    <v-container :fluid="content.fullWidth">
      <div
        v-if="content.htmlContent"
        class="custom-html-content"
        v-html="content.htmlContent"
      ></div>

      <div v-else class="text-center py-8">
        <v-icon size="64" color="grey">mdi-code-tags</v-icon>
        <p class="text-h6 text-grey mt-4">No custom HTML content provided</p>
        <p class="text-caption text-grey">Add your HTML content in the widget editor</p>
      </div>
    </v-container>
  </v-sheet>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      htmlContent: '',
      containerClass: '',
      containerStyle: '',
      fullWidth: false
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});

// Note: Using v-html can be a security risk if the content comes from untrusted sources.
// Make sure to sanitize HTML content on the backend before saving.
// Consider using a library like DOMPurify for additional client-side sanitization.
</script>

<style scoped>
.custom-html-content {
  /* Base styles for custom HTML content */
  line-height: 1.6;
}

/* Deep selector to style elements inside v-html */
.custom-html-content :deep(h1),
.custom-html-content :deep(h2),
.custom-html-content :deep(h3),
.custom-html-content :deep(h4),
.custom-html-content :deep(h5),
.custom-html-content :deep(h6) {
  margin-bottom: 1rem;
  font-weight: bold;
}

.custom-html-content :deep(p) {
  margin-bottom: 1rem;
}

.custom-html-content :deep(a) {
  color: rgb(var(--v-theme-primary));
  text-decoration: none;
}

.custom-html-content :deep(a:hover) {
  text-decoration: underline;
}

.custom-html-content :deep(img) {
  max-width: 100%;
  height: auto;
}

.custom-html-content :deep(ul),
.custom-html-content :deep(ol) {
  margin-bottom: 1rem;
  padding-left: 2rem;
}

.custom-html-content :deep(li) {
  margin-bottom: 0.5rem;
}

.custom-html-content :deep(blockquote) {
  border-left: 4px solid rgb(var(--v-theme-primary));
  padding-left: 1rem;
  margin: 1rem 0;
  font-style: italic;
  color: rgba(0, 0, 0, 0.6);
}

.custom-html-content :deep(code) {
  background: rgba(0, 0, 0, 0.05);
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.9em;
}

.custom-html-content :deep(pre) {
  background: rgba(0, 0, 0, 0.05);
  padding: 1rem;
  border-radius: 4px;
  overflow-x: auto;
  margin-bottom: 1rem;
}

.custom-html-content :deep(pre code) {
  background: transparent;
  padding: 0;
}

.custom-html-content :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
}

.custom-html-content :deep(th),
.custom-html-content :deep(td) {
  border: 1px solid rgba(0, 0, 0, 0.12);
  padding: 0.75rem;
  text-align: left;
}

.custom-html-content :deep(th) {
  background: rgba(0, 0, 0, 0.05);
  font-weight: bold;
}
</style>
