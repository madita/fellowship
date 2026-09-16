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
            <div v-if="link.description" class="text-body-2 text-medium-emphasis">
              {{ link.description }}
            </div>
            <v-chip
              v-if="link.badge"
              :color="link.badgeColor || 'primary'"
              variant="tonal"
              size="small"
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
            <v-chip :color="link.badgeColor || 'primary'" variant="tonal" size="small">
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
          :variant="buttonVariant"
          :block="config.block"
          :size="buttonSize"
          class="quick-link-button mb-2"
        >
          <v-icon v-if="link.icon" start>{{ link.icon }}</v-icon>
          {{ link.title }}
          <v-chip
            v-if="link.badge"
            :color="link.badgeColor || 'white'"
            variant="tonal"
            size="small"
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

const buttonVariant = computed(() => {
  if (props.config.text) return 'text'
  if (props.config.outlined) return 'outlined'
  return 'elevated'
})

const buttonSize = computed(() => {
  if (props.config.xlarge) return 'x-large'
  if (props.config.large) return 'large'
  return 'default'
})

const subtitleClass = computed(() => {
  return props.config.subtitleClass || 'text-center text-medium-emphasis'
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
  border-left: 4px solid rgb(var(--v-theme-primary));
}

.quick-link-card.border-green {
  border-left: 4px solid rgb(var(--v-theme-success));
}

.quick-link-card.border-purple {
  border-left: 4px solid rgb(var(--v-theme-secondary));
}

.quick-link-card.border-red {
  border-left: 4px solid rgb(var(--v-theme-error));
}

.quick-link-card.border-orange {
  border-left: 4px solid rgb(var(--v-theme-warning));
}

.quick-link-list-item {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.quick-link-list-item:last-child {
  border-bottom: none;
}

.quick-link-button {
  width: 100%;
}
</style>
