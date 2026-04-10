<template>
  <v-container class="py-12">
    <v-row justify="center">
      <v-col
        v-for="(plan, index) in content.plans"
        :key="index"
        cols="12"
        sm="6"
        md="4"
      >
        <v-card
          :variant="plan.highlighted ? 'elevated' : 'outlined'"
          :elevation="plan.highlighted ? 8 : 0"
          class="pa-6 text-center"
          :class="{ 'border-primary': plan.highlighted }"
        >
          <h3 class="text-h5 mb-2">{{ plan.name }}</h3>
          <div class="text-h3 font-weight-bold my-4">
            ${{ plan.price }}
            <span class="text-body-2 text-grey">/{{ plan.period }}</span>
          </div>
          <v-divider class="my-4" />
          <v-list density="compact" class="mb-4">
            <v-list-item v-for="(feature, i) in plan.features" :key="i">
              <template #prepend>
                <v-icon icon="mdi-check" color="success" size="small" />
              </template>
              {{ feature }}
            </v-list-item>
          </v-list>
          <v-btn
            :to="plan.buttonLink"
            :color="plan.highlighted ? 'primary' : 'default'"
            :variant="plan.highlighted ? 'elevated' : 'outlined'"
            block
            size="large"
          >
            {{ plan.buttonText }}
          </v-btn>
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
      plans: [
        {
          name: 'Basic',
          price: '9.99',
          period: 'month',
          features: ['1 User', '10 Projects'],
          highlighted: false,
          buttonText: 'Get Started',
          buttonLink: '/signup',
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
