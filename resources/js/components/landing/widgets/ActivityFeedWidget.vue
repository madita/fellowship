<template>
  <v-container :class="containerClass">
    <div class="d-flex justify-space-between align-center mb-4">
      <div>
        <div v-if="content.title" class="text-h5 font-weight-bold">
          {{ content.title }}
        </div>
        <div v-if="content.subtitle" class="text-subtitle-2 text--secondary">
          {{ content.subtitle }}
        </div>
      </div>
      <v-btn
        v-if="content.viewAllUrl"
        :href="content.viewAllUrl"
        :to="content.viewAllInternal ? content.viewAllUrl : undefined"
        text
        small
        color="primary"
      >
        {{ content.viewAllText || 'View All' }}
        <v-icon right small>mdi-arrow-right</v-icon>
      </v-btn>
    </div>

    <v-card :elevation="config.elevation || 1">
      <v-list :two-line="config.twoLine !== false" :three-line="config.threeLine">
        <template v-for="(item, index) in activities">
          <v-list-item
            :key="`item-${index}`"
            :to="item.internal ? item.url : undefined"
            :href="!item.internal ? safeUrl(item.url) : undefined"
            :target="!item.internal && item.external ? '_blank' : undefined"
            :rel="!item.internal && item.external ? 'noopener noreferrer' : undefined"
          >
            <template v-if="config.showAvatar !== false" #prepend>
              <v-avatar>
                <v-img v-if="item.avatar" :src="item.avatar"></v-img>
                <v-icon v-else :color="item.iconColor || 'grey'">
                  {{ item.icon || 'mdi-circle-outline' }}
                </v-icon>
              </v-avatar>
            </template>

            <v-list-item-title>
              {{ item.title }}
              <v-chip
                v-if="item.badge"
                :color="item.badgeColor || 'primary'"
                size="x-small"
                class="ml-2"
              >
                {{ item.badge }}
              </v-chip>
            </v-list-item-title>
            <v-list-item-subtitle v-if="item.subtitle">
              {{ item.subtitle }}
            </v-list-item-subtitle>
            <v-list-item-subtitle v-if="item.description && config.threeLine">
              {{ item.description }}
            </v-list-item-subtitle>

            <template v-if="item.timestamp" #append>
              <span class="text-caption text-medium-emphasis">{{ item.timestamp }}</span>
            </template>
          </v-list-item>

          <v-divider
            v-if="index < activities.length - 1"
            :key="`divider-${index}`"
          ></v-divider>
        </template>

        <v-list-item v-if="activities.length === 0">
          <v-list-item-title class="text-center text-medium-emphasis">
            {{ content.emptyText || 'No recent activity' }}
          </v-list-item-title>
        </v-list-item>
      </v-list>
    </v-card>
  </v-container>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Recent Activity',
      subtitle: 'What\'s happening',
      viewAllUrl: '/activity',
      viewAllText: 'View All',
      viewAllInternal: true,
      emptyText: 'No recent activity',
      items: [
        {
          title: 'New forum post in General Discussion',
          subtitle: 'by John Doe',
          description: 'Check out this amazing feature...',
          timestamp: '2 min ago',
          icon: 'mdi-forum',
          iconColor: 'blue',
          avatar: '',
          url: '/forum/1',
          internal: true,
          external: false,
          badge: '',
          badgeColor: 'primary'
        },
        {
          title: 'Event: Team Meeting',
          subtitle: 'Tomorrow at 10:00 AM',
          description: 'Monthly team sync',
          timestamp: '1 hour ago',
          icon: 'mdi-calendar',
          iconColor: 'green',
          avatar: '',
          url: '/events/1',
          internal: true,
          external: false,
          badge: 'Tomorrow',
          badgeColor: 'orange'
        },
        {
          title: 'New ticket submitted',
          subtitle: 'Bug Report #123',
          description: 'Login page not loading',
          timestamp: '3 hours ago',
          icon: 'mdi-ticket',
          iconColor: 'red',
          avatar: '',
          url: '/tickets/123',
          internal: true,
          external: false,
          badge: 'High',
          badgeColor: 'red'
        }
      ]
    })
  },
  config: {
    type: Object,
    default: () => ({
      elevation: 1,
      twoLine: true,
      threeLine: false,
      showAvatar: true,
      maxItems: 10,
      containerClass: 'py-4'
    })
  }
})

const containerClass = computed(() => {
  return props.config.containerClass || 'py-4'
})

const activities = computed(() => {
  const items = props.content.items || []
  const max = props.config.maxItems ?? 10
  return items.slice(0, max)
})
</script>

<style scoped>
.v-list-item {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.v-list-item:hover {
  background-color: rgba(0, 0, 0, 0.02);
}
</style>
