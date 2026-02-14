<template>
  <v-container class="py-8">
    <v-row>
      <v-col
        v-for="(stat, index) in content.stats"
        :key="index"
        cols="6"
        md="3"
        class="text-center"
      >
        <div class="text-h3 text-primary font-weight-bold">{{ stat.value }}</div>
        <div class="text-h6 mt-2">{{ stat.title }}</div>
        <div v-if="stat.description" class="text-body-2 text-grey mt-1">
          {{ stat.description }}
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
      stats: [
        { title: 'Projects', value: '4,253', description: '' },
        { title: 'Users', value: '1,283,787', description: '' },
        { title: 'Subscribers', value: '1,348', description: '' },
        { title: 'Businesses', value: '331,234', description: '' },
      ],
    }),
  },
  config: {
    type: Object,
    default: () => ({}),
  },
});
</script>
