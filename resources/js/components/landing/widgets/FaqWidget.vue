<template>
  <v-container class="py-8">
    <h2 v-if="content.title" class="text-h4 text-center mb-8">{{ content.title }}</h2>
    <v-expansion-panels variant="accordion">
      <v-expansion-panel
        v-for="(faq, index) in content.faqs"
        :key="index"
      >
        <v-expansion-panel-title>
          {{ faq.question }}
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          {{ faq.answer }}
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </v-container>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Frequently Asked Questions',
      faqs: [
        {
          question: 'How do I get started?',
          answer: 'Getting started is easy! Simply sign up for an account.',
        },
      ],
    }),
  },
  config: {
    type: Object,
    default: () => ({}),
  },
});
</script>
