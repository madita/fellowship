<template>
  <v-container class="py-12">
    <v-row>
      <v-col
        v-for="(testimonial, index) in content.testimonials"
        :key="index"
        cols="12"
        md="6"
      >
        <v-card variant="outlined" class="pa-6">
          <div class="d-flex align-center mb-4">
            <v-avatar v-if="testimonial.avatar" :image="testimonial.avatar" size="48" class="mr-4" />
            <v-avatar v-else color="primary" size="48" class="mr-4">
              <span class="text-white">{{ testimonial.author?.charAt(0) }}</span>
            </v-avatar>
            <div>
              <div class="font-weight-bold">{{ testimonial.author }}</div>
              <div class="text-caption text-grey">{{ testimonial.role }}</div>
            </div>
          </div>
          <p class="text-body-1 font-italic">"{{ testimonial.quote }}"</p>
          <div v-if="testimonial.rating" class="mt-3">
            <v-icon
              v-for="star in 5"
              :key="star"
              :icon="star <= testimonial.rating ? 'mdi-star' : 'mdi-star-outline'"
              :color="star <= testimonial.rating ? 'amber' : 'grey'"
              size="small"
            />
          </div>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      testimonials: [
        {
          quote: 'This is an amazing platform!',
          author: 'John Doe',
          role: 'CEO, Company Inc',
          avatar: null,
          rating: 5,
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
