<template>
  <v-container :class="containerClass">
    <div v-if="content.title" class="text-h6 font-weight-bold mb-4" :class="titleClass">
      {{ content.title }}
    </div>

    <div :class="linksClass">
      <v-btn
        v-for="(social, index) in content.links"
        :key="index"
        :href="social.url"
        target="_blank"
        :icon="config.style === 'icon'"
        :fab="config.style === 'fab'"
        :text="config.style === 'text'"
        :outlined="config.style === 'outlined'"
        :color="social.color || config.defaultColor || 'grey darken-1'"
        :size="config.size || 'default'"
        :class="buttonClass"
        :aria-label="social.name"
      >
        <v-icon :size="config.iconSize">{{ social.icon }}</v-icon>
        <span v-if="config.showLabels && config.style !== 'icon'" class="ml-2">
          {{ social.name }}
        </span>
      </v-btn>
    </div>

    <div v-if="content.subtitle" class="text-caption text--secondary mt-3 text-center">
      {{ content.subtitle }}
    </div>
  </v-container>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Follow Us',
      subtitle: 'Stay connected with our community',
      links: [
        {
          name: 'Twitter',
          url: 'https://twitter.com',
          icon: 'mdi-twitter',
          color: '#1DA1F2'
        },
        {
          name: 'Facebook',
          url: 'https://facebook.com',
          icon: 'mdi-facebook',
          color: '#1877F2'
        },
        {
          name: 'LinkedIn',
          url: 'https://linkedin.com',
          icon: 'mdi-linkedin',
          color: '#0A66C2'
        },
        {
          name: 'GitHub',
          url: 'https://github.com',
          icon: 'mdi-github',
          color: '#181717'
        },
        {
          name: 'Discord',
          url: 'https://discord.com',
          icon: 'mdi-discord',
          color: '#5865F2'
        },
        {
          name: 'YouTube',
          url: 'https://youtube.com',
          icon: 'mdi-youtube',
          color: '#FF0000'
        }
      ]
    })
  },
  config: {
    type: Object,
    default: () => ({
      style: 'icon',           // 'icon', 'fab', 'text', 'outlined'
      size: 'default',         // 'x-small', 'small', 'default', 'large', 'x-large'
      iconSize: 24,            // Icon size in pixels
      alignment: 'center',     // 'start', 'center', 'end'
      showLabels: false,       // Show social network names
      defaultColor: 'grey darken-1',
      containerClass: 'py-4',
      titleClass: 'text-center',
      spacing: 2               // Spacing between buttons (1-5)
    })
  }
})

const containerClass = computed(() => {
  return props.config.containerClass || 'py-4'
})

const titleClass = computed(() => {
  return props.config.titleClass || 'text-center'
})

const linksClass = computed(() => {
  const classes = ['d-flex', 'flex-wrap']
  
  if (props.config.alignment === 'center') {
    classes.push('justify-center')
  } else if (props.config.alignment === 'end') {
    classes.push('justify-end')
  } else {
    classes.push('justify-start')
  }
  
  return classes.join(' ')
})

const buttonClass = computed(() => {
  const spacing = props.config.spacing || 2
  return `ma-${spacing}`
})
</script>

<style scoped>
.v-btn {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.v-btn:hover {
  transform: translateY(-2px);
}
</style>
