<template>
  <v-container class="py-12">
    <!-- Header -->
    <div class="text-center mb-8">
      <h2 class="text-h3 text-md-h2 font-weight-bold mb-3">
        {{ content.title }}
      </h2>
      <p v-if="content.subtitle" class="text-h6 text-medium-emphasis">
        {{ content.subtitle }}
      </p>
    </div>

    <!-- Loading -->
    <loading-state v-if="loading" compact />

    <template v-else>
      <!-- Stats Row -->
      <v-row v-if="content.showStats" class="mb-8">
        <v-col cols="4" class="text-center">
          <div class="text-h3 text-primary font-weight-bold">{{ totalCategories }}</div>
          <div class="text-h6 mt-1">{{ t('widgets.landing.categories') }}</div>
        </v-col>
        <v-col cols="4" class="text-center">
          <div class="text-h3 text-primary font-weight-bold">{{ totalThreads }}</div>
          <div class="text-h6 mt-1">{{ t('widgets.landing.threads') }}</div>
        </v-col>
        <v-col cols="4" class="text-center">
          <div class="text-h3 text-primary font-weight-bold">{{ totalPosts }}</div>
          <div class="text-h6 mt-1">{{ t('widgets.landing.posts') }}</div>
        </v-col>
      </v-row>

      <!-- Categories & Activity in 2-column layout -->
      <v-row>
        <!-- Top Categories -->
        <v-col v-if="content.showCategories" cols="12" :md="content.showActivity ? 7 : 12">
          <h3 class="text-h6 font-weight-bold mb-4">{{ t('widgets.landing.popularCategories') }}</h3>
          <v-card
            v-for="cat in displayedCategories"
            :key="cat.id"
            class="forum-cat-card mb-2"
            variant="outlined"
            rounded="lg"
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
                {{ t('widgets.landing.threadsCount', { count: cat.threads_count || 0 }) }}
              </v-chip>
            </v-card-text>
          </v-card>

          <empty-state
            v-if="!displayedCategories.length"
            compact
            icon="mdi-forum-outline"
            :title="t('widgets.landing.noCategories')"
          />
        </v-col>

        <!-- Recent Activity -->
        <v-col v-if="content.showActivity" cols="12" :md="content.showCategories ? 5 : 12">
          <h3 class="text-h6 font-weight-bold mb-4">{{ t('widgets.landing.recentActivity') }}</h3>
          <v-card variant="outlined" rounded="lg" class="activity-card">
            <activity-feed log="forum" :per-page="content.activitiesToShow || 5" />
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
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import ActivityFeed from '@/components/common/ActivityFeed.vue';

const { t } = useI18n();

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

const totalCategories = computed(() => categories.value.length);
const totalThreads = computed(() => categories.value.reduce((sum, c) => sum + (c.threads_count || 0), 0));
const totalPosts = computed(() => categories.value.reduce((sum, c) => sum + (c.posts_count || 0), 0));

const displayedCategories = computed(() => {
  const limit = props.content.categoriesToShow || 5;
  return categories.value.slice(0, limit);
});

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/forums');
    categories.value = data || [];
  } catch (e) {
    console.error('ForumWidget: failed to load data', e);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.forum-cat-card {
  transition: all 0.2s ease;
}

.forum-cat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-on-surface), 0.1) !important;
}
</style>
