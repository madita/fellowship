<template>
  <v-container :class="containerClass">
    <div v-if="content.title" class="text-h5 font-weight-bold mb-4" :class="titleClass">
      {{ content.title }}
    </div>
    <div v-if="content.subtitle" class="text-subtitle-1 mb-6" :class="subtitleClass">
      {{ content.subtitle }}
    </div>

    <v-row :justify="config.alignment || 'start'">
      <v-col
        v-for="(link, index) in content.links"
        :key="index"
        :cols="config.cols || 12"
        :sm="config.sm || 6"
        :md="config.md || 4"
        :lg="config.lg || 3"
      >
        <v-card
          v-if="config.style === 'card'"
          :href="link.url"
          :to="link.internal ? link.url : undefined"
          :target="link.external ? '_blank' : undefined"
          :elevation="config.elevation || 2"
          :hover="config.hover !== false"
          class="quick-link-card h-100"
          :class="link.color ? `border-${link.color}` : ''"
        >
          <v-card-text class="text-center pa-6">
            <v-icon
              v-if="link.icon"
              :size="config.iconSize || 48"
              :color="link.color || config.iconColor || 'primary'"
              class="mb-3"
            >
              {{ link.icon }}
            </v-icon>
            <div class="text-h6 font-weight-bold mb-2">{{ link.title }}</div>
            <div v-if="link.description" class="text-body-2 text--secondary">
              {{ link.description }}
            </div>
            <v-chip
              v-if="link.badge"
              :color="link.badgeColor || 'primary'"
              small
              class="mt-2"
            >
              {{ link.badge }}
            </v-chip>
          </v-card-text>
        </v-card>

        <v-list-item
          v-else-if="config.style === 'list'"
          :href="link.url"
          :to="link.internal ? link.url : undefined"
          :target="link.external ? '_blank' : undefined"
          class="quick-link-list-item"
        >
          <template v-if="link.icon" #prepend>
            <v-icon :color="link.color || config.iconColor">{{ link.icon }}</v-icon>
          </template>
          <v-list-item-title>{{ link.title }}</v-list-item-title>
          <v-list-item-subtitle v-if="link.description">
            {{ link.description }}
          </v-list-item-subtitle>
          <template v-if="link.badge" #append>
            <v-chip :color="link.badgeColor || 'primary'" size="small">
              {{ link.badge }}
            </v-chip>
          </template>
        </v-list-item>

        <v-btn
          v-else
          :href="link.url"
          :to="link.internal ? link.url : undefined"
          :target="link.external ? '_blank' : undefined"
          :color="link.color || config.buttonColor || 'primary'"
          :outlined="config.outlined"
          :text="config.text"
          :block="config.block"
          :large="config.large"
          :x-large="config.xlarge"
          class="quick-link-button mb-2"
        >
          <v-icon v-if="link.icon" left>{{ link.icon }}</v-icon>
          {{ link.title }}
          <v-chip
            v-if="link.badge"
            :color="link.badgeColor || 'white'"
            small
            class="ml-2"
          >
            {{ link.badge }}
          </v-chip>
        </v-btn>
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
      title: 'Quick Links',
      subtitle: '',
      links: [
        {
          title: 'Documentation',
          description: 'Read the docs',
          url: '/docs',
          icon: 'mdi-book-open-variant',
          color: 'blue',
          internal: true,
          external: false,
          badge: '',
          badgeColor: 'primary'
        },
        {
          title: 'Support',
          description: 'Get help',
          url: '/support',
          icon: 'mdi-help-circle',
          color: 'green',
          internal: true,
          external: false,
          badge: '',
          badgeColor: 'primary'
        },
        {
          title: 'Community',
          description: 'Join the discussion',
          url: '/forum',
          icon: 'mdi-forum',
          color: 'purple',
          internal: true,
          external: false,
          badge: 'New',
          badgeColor: 'red'
        }
      ]
    })
  },
  config: {
    type: Object,
    default: () => ({
      style: 'card',          // 'card', 'list', 'button'
      cols: 12,               // Grid columns
      sm: 6,
      md: 4,
      lg: 3,
      alignment: 'start',     // 'start', 'center', 'end'
      elevation: 2,           // Card elevation
      hover: true,            // Card hover effect
      iconSize: 48,           // Icon size
      iconColor: 'primary',   // Default icon color
      buttonColor: 'primary', // Default button color
      outlined: false,        // Outlined buttons
      text: false,            // Text buttons
      block: false,           // Block buttons
      large: false,           // Large buttons
      xlarge: false           // X-Large buttons
    })
  }
})

const containerClass = computed(() => {
  return props.config.containerClass || 'py-8'
})

const titleClass = computed(() => {
  return props.config.titleClass || 'text-center'
})

const subtitleClass = computed(() => {
  return props.config.subtitleClass || 'text-center text--secondary'
})
</script>

<style scoped>
.quick-link-card {
  transition: all 0.3s ease;
  cursor: pointer;
}

.quick-link-card:hover {
  transform: translateY(-4px);
}

.quick-link-card.border-blue {
  border-left: 4px solid #2196F3;
}

.quick-link-card.border-green {
  border-left: 4px solid #4CAF50;
}

.quick-link-card.border-purple {
  border-left: 4px solid #9C27B0;
}

.quick-link-card.border-red {
  border-left: 4px solid #F44336;
}

.quick-link-card.border-orange {
  border-left: 4px solid #FF9800;
}

.quick-link-list-item {
  border-bottom: 1px solid #e0e0e0;
}

.quick-link-list-item:last-child {
  border-bottom: none;
}

.quick-link-button {
  width: 100%;
}
</style>
