<template>
  <v-container class="py-12">
    <!-- Header -->
    <div class="text-center mb-8">
      <h2 class="text-h3 text-md-h2 font-weight-bold mb-3">
        {{ content.title }}
      </h2>
      <p v-if="content.subtitle" class="text-h6 text-grey">
        {{ content.subtitle }}
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-8">
      <v-progress-circular size="48" indeterminate color="primary" />
    </div>

    <template v-else>
      <!-- Stats Row -->
      <v-row v-if="content.showStats" class="mb-8">
        <v-col cols="4" class="text-center">
          <div class="text-h3 text-primary font-weight-bold">{{ totalCategories }}</div>
          <div class="text-h6 mt-1">Categories</div>
        </v-col>
        <v-col cols="4" class="text-center">
          <div class="text-h3 text-primary font-weight-bold">{{ totalThreads }}</div>
          <div class="text-h6 mt-1">Threads</div>
        </v-col>
        <v-col cols="4" class="text-center">
          <div class="text-h3 text-primary font-weight-bold">{{ totalPosts }}</div>
          <div class="text-h6 mt-1">Posts</div>
        </v-col>
      </v-row>

      <!-- Categories & Activity in 2-column layout -->
      <v-row>
        <!-- Top Categories -->
        <v-col v-if="content.showCategories" cols="12" :md="content.showActivity ? 7 : 12">
          <h3 class="text-h5 font-weight-bold mb-4">Popular Categories</h3>
          <v-card
            v-for="cat in displayedCategories"
            :key="cat.id"
            class="forum-cat-card mb-2"
            variant="outlined"
            :href="'/forum/' + cat.slug"
          >
            <v-card-text class="d-flex align-center py-3">
              <v-avatar color="primary" size="40" class="mr-3">
                <v-icon color="white" size="20">mdi-forum</v-icon>
              </v-avatar>
              <div class="flex-grow-1">
                <div class="font-weight-bold text-body-1">{{ cat.name }}</div>
                <div v-if="cat.description" class="text-caption text-medium-emphasis text-truncate" style="max-width: 400px;">
                  {{ cat.description }}
                </div>
              </div>
              <v-chip size="small" variant="tonal" color="primary" class="ml-2">
                {{ cat.threads_count || 0 }} threads
              </v-chip>
            </v-card-text>
          </v-card>

          <div v-if="!displayedCategories.length" class="text-center text-medium-emphasis py-4">
            No forum categories yet.
          </div>
        </v-col>

        <!-- Recent Activity -->
        <v-col v-if="content.showActivity" cols="12" :md="content.showCategories ? 5 : 12">
          <h3 class="text-h5 font-weight-bold mb-4">Recent Activity</h3>
          <v-card variant="outlined" class="activity-card">
            <v-list v-if="displayedActivities.length" density="compact" class="py-0">
              <v-list-item
                v-for="activity in displayedActivities"
                :key="activity.id"
                class="activity-item"
              >
                <template v-slot:prepend>
                  <v-avatar size="32" color="primary" class="mr-2">
                    <span class="text-caption text-white font-weight-bold">
                      {{ (activity.causer?.username || '?')[0].toUpperCase() }}
                    </span>
                  </v-avatar>
                </template>
                <v-list-item-title class="text-body-2">
                  <strong>{{ activity.causer?.username }}</strong>
                  {{ getActivityText(activity) }}
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  {{ formatTime(activity.created_at) }}
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>
            <v-card-text v-else class="text-center text-medium-emphasis py-6">
              No recent activity.
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- CTA Button -->
      <div v-if="content.buttonText" class="text-center mt-8">
        <v-btn
          color="primary"
          size="large"
          variant="elevated"
          :href="content.buttonLink || '/forum'"
          prepend-icon="mdi-forum"
        >
          {{ content.buttonText }}
        </v-btn>
      </div>
    </template>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Community Forum',
      subtitle: 'Join the discussion',
      categoriesToShow: 5,
      activitiesToShow: 5,
      showStats: true,
      showCategories: true,
      showActivity: true,
      buttonText: 'Visit Forum',
      buttonLink: '/forum',
    }),
  },
  config: {
    type: Object,
    default: () => ({}),
  },
});

const loading = ref(true);
const categories = ref([]);
const activities = ref([]);

const totalCategories = computed(() => categories.value.length);
const totalThreads = computed(() => categories.value.reduce((sum, c) => sum + (c.threads_count || 0), 0));
const totalPosts = computed(() => categories.value.reduce((sum, c) => sum + (c.posts_count || 0), 0));

const displayedCategories = computed(() => {
  const limit = props.content.categoriesToShow || 5;
  return categories.value.slice(0, limit);
});

const displayedActivities = computed(() => {
  const limit = props.content.activitiesToShow || 5;
  return activities.value.slice(0, limit);
});

function getActivityText(activity) {
  const title = activity.properties?.thread_title || '';
  switch (activity.event) {
    case 'thread_created':
      return `started a new thread: "${title}"`;
    case 'post_created':
      return `replied in "${title}"`;
    case 'post_liked':
      return `liked a post in "${title}"`;
    case 'solution_marked':
      return `marked a solution in "${title}"`;
    default:
      return activity.description || '';
  }
}

function formatTime(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now - date;
  const diffMin = Math.floor(diffMs / 60000);
  if (diffMin < 1) return 'just now';
  if (diffMin < 60) return `${diffMin}m ago`;
  const diffHrs = Math.floor(diffMin / 60);
  if (diffHrs < 24) return `${diffHrs}h ago`;
  const diffDays = Math.floor(diffHrs / 24);
  if (diffDays < 30) return `${diffDays}d ago`;
  return date.toLocaleDateString();
}

onMounted(async () => {
  try {
    const [forumsRes, activityRes] = await Promise.all([
      axios.get('/api/forums'),
      axios.get('/api/activity', { params: { log: 'forum' } }),
    ]);
    categories.value = forumsRes.data || [];
    activities.value = activityRes.data?.data || [];
  } catch (e) {
    console.error('ForumWidget: failed to load data', e);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.forum-cat-card {
  border-radius: 12px !important;
  transition: all 0.2s ease;
}

.forum-cat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-on-surface), 0.1) !important;
}

.activity-card {
  border-radius: 12px !important;
}

.activity-item {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.activity-item:last-child {
  border-bottom: none;
}
</style>
