<template>
  <v-container :class="containerClass">
    <div v-if="content.title" class="text-h5 font-weight-bold mb-2">
      {{ content.title }}
    </div>
    <div v-if="content.subtitle" class="text-subtitle-2 text--secondary mb-6">
      {{ content.subtitle }}
    </div>

    <v-row>
      <v-col
        v-for="(stat, index) in content.stats"
        :key="index"
        :cols="config.cols || 6"
        :sm="config.sm || 6"
        :md="config.md || 3"
      >
        <v-card
          :elevation="config.elevation || 2"
          :outlined="config.outlined"
          :hover="config.hover"
          :color="stat.color"
          :dark="stat.dark"
          :href="stat.url"
          :to="stat.internal ? stat.url : undefined"
          class="stat-card h-100"
        >
          <v-card-text class="pa-4">
            <div class="d-flex justify-space-between align-start">
              <div class="flex-grow-1">
                <div class="text-overline mb-1" :class="stat.dark ? 'white--text' : 'text--secondary'">
                  {{ stat.label }}
                </div>
                <div class="text-h4 font-weight-bold mb-1">
                  {{ formatValue(stat.value, stat.format) }}
                </div>
                <div v-if="stat.change !== undefined" class="d-flex align-center mt-2">
                  <v-icon
                    :color="getChangeColor(stat.change)"
                    small
                  >
                    {{ getChangeIcon(stat.change) }}
                  </v-icon>
                  <span
                    :class="`${getChangeColor(stat.change)}--text`"
                    class="text-caption ml-1 font-weight-medium"
                  >
                    {{ Math.abs(stat.change) }}%
                  </span>
                  <span class="text-caption ml-1" :class="stat.dark ? 'white--text' : 'text--secondary'">
                    {{ stat.changePeriod || 'vs last period' }}
                  </span>
                </div>
                <div v-if="stat.description" class="text-caption mt-2" :class="stat.dark ? 'white--text' : 'text--secondary'">
                  {{ stat.description }}
                </div>
              </div>
              <v-icon
                v-if="stat.icon"
                :size="config.iconSize || 48"
                :color="stat.iconColor || (stat.dark ? 'white' : 'grey lighten-1')"
                class="ml-2"
              >
                {{ stat.icon }}
              </v-icon>
            </div>

            <v-progress-linear
              v-if="stat.progress !== undefined"
              :value="stat.progress"
              :color="stat.progressColor || 'primary'"
              height="4"
              rounded
              class="mt-3"
            ></v-progress-linear>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Dashboard Overview',
      subtitle: 'Your activity summary',
      stats: [
        {
          label: 'Total Posts',
          value: 1234,
          format: 'number',
          change: 12.5,
          changePeriod: 'this month',
          description: 'Forum posts created',
          icon: 'mdi-forum',
          iconColor: 'blue',
          color: '',
          dark: false,
          url: '/posts',
          internal: true,
          progress: 75,
          progressColor: 'blue'
        },
        {
          label: 'Events Attended',
          value: 42,
          format: 'number',
          change: -5,
          changePeriod: 'this month',
          description: 'Events you joined',
          icon: 'mdi-calendar-check',
          iconColor: 'green',
          color: '',
          dark: false,
          url: '/events',
          internal: true
        },
        {
          label: 'Tickets Open',
          value: 7,
          format: 'number',
          change: 0,
          changePeriod: 'this week',
          description: 'Active support tickets',
          icon: 'mdi-ticket',
          iconColor: 'orange',
          color: '',
          dark: false,
          url: '/tickets',
          internal: true
        },
        {
          label: 'Reputation',
          value: 9876,
          format: 'number',
          change: 23,
          changePeriod: 'this week',
          description: 'Community points',
          icon: 'mdi-star',
          iconColor: 'yellow darken-2',
          color: '',
          dark: false,
          url: '/profile',
          internal: true,
          progress: 87,
          progressColor: 'yellow'
        }
      ]
    })
  },
  config: {
    type: Object,
    default: () => ({
      cols: 6,
      sm: 6,
      md: 3,
      elevation: 2,
      outlined: false,
      hover: true,
      iconSize: 48,
      containerClass: 'py-4'
    })
  }
})

const containerClass = computed(() => {
  return props.config.containerClass || 'py-4'
})

const formatValue = (value, format) => {
  if (format === 'currency') {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value)
  } else if (format === 'percentage') {
    return `${value}%`
  } else if (format === 'number') {
    return new Intl.NumberFormat('en-US').format(value)
  }
  return value
}

const getChangeColor = (change) => {
  if (change > 0) return 'success'
  if (change < 0) return 'error'
  return 'grey'
}

const getChangeIcon = (change) => {
  if (change > 0) return 'mdi-trending-up'
  if (change < 0) return 'mdi-trending-down'
  return 'mdi-minus'
}
</script>

<style scoped>
.stat-card {
  transition: all 0.3s ease;
  cursor: pointer;
}

.stat-card:hover {
  transform: translateY(-4px);
}
</style>
