<template>
  <v-container class="py-8">
    <v-row>
      <v-col
        v-for="(feature, index) in content.features"
        :key="index"
        cols="12"
        sm="6"
        md="3"
        class="text-center"
      >
        <v-icon :icon="feature.icon" size="48" color="primary" class="mb-4" />
        <h3 class="text-h6 mb-2">{{ feature.title }}</h3>
        <p class="text-body-2 text-grey">{{ feature.description }}</p>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      features: [
        { icon: 'mdi-account-check-outline', title: 'Account Verification', description: 'Lorem ipsum dolor sit amet' },
        { icon: 'mdi-lifebuoy', title: 'Dedicated Support', description: 'Lorem ipsum dolor sit amet' },
        { icon: 'mdi-email-outline', title: 'Email Integration', description: 'Lorem ipsum dolor sit amet' },
        { icon: 'mdi-clock-outline', title: 'Save Time', description: 'Lorem ipsum dolor sit amet' },
      ],
    }),
  },
  config: {
    type: Object,
    default: () => ({}),
  },
});
</script>
