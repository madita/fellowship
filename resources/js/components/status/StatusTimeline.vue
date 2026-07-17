<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import StatusComposer from './StatusComposer.vue';
import StatusCard from './StatusCard.vue';
import UserAvatar from '../common/UserAvatar.vue';
import { useUserStore } from '@/store/userStore.js';

const { t } = useI18n();
const userStore = useUserStore();

const user = computed(() => userStore.user || { id: null });

const statuses = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const page = ref(1);
const hasMore = ref(true);

const filterUserId = ref(null); // For user-specific timeline

const loadTimeline = async (append = false) => {
    if (append) {
        loadingMore.value = true;
    } else {
        loading.value = true;
    }

    try {
        const params = {
            page: page.value,
            per_page: 10,
        };

        if (filterUserId.value) {
            params.user_id = filterUserId.value;
        }

        const response = await axios.get('/api/statuses', { params });

        if (append) {
            statuses.value.push(...response.data.data);
        } else {
            statuses.value = response.data.data;
        }

        hasMore.value = response.data.current_page < response.data.last_page;
    } catch (error) {
        console.error('Failed to load timeline:', error);
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
};

const loadMore = () => {
    if (hasMore.value && !loadingMore.value) {
        page.value++;
        loadTimeline(true);
    }
};

const handleStatusPosted = (newStatus) => {
    statuses.value.unshift(newStatus);
};

const handleStatusUpdated = (updatedStatus) => {
    const index = statuses.value.findIndex(s => s.id === updatedStatus.id);
    if (index !== -1) {
        statuses.value[index] = updatedStatus;
    }
};

const handleStatusDeleted = (statusId) => {
    statuses.value = statuses.value.filter(s => s.id !== statusId);
};

onMounted(() => {
    loadTimeline();
});
</script>

<template>
    <div class="status-timeline-container">
        <!-- Page Header -->
        <div class="timeline-header">
            <div class="timeline-header-inner">
                <p class="timeline-eyebrow">{{ t('timeline.eyebrow') }}</p>
                <h1 class="text-h4 text-md-h3 font-weight-bold timeline-title">{{ t('timeline.title') }}</h1>
                <p class="text-subtitle-1 text-medium-emphasis mb-0">
                    {{ t('timeline.subtitle') }}
                </p>
            </div>
        </div>

        <v-container class="timeline-body py-6 py-md-8">
            <v-row justify="center">
                <!-- Aside: context rail (desktop only) -->
                <v-col cols="12" md="4" lg="3" class="d-none d-md-block">
                    <div class="timeline-aside">
                        <v-card rounded="lg" variant="flat" class="aside-card pa-4">
                            <div v-if="user.id" class="d-flex align-center mb-3">
                                <UserAvatar :user="user" size="52" class="mr-3" />
                                <div class="min-w-0">
                                    <div class="text-caption text-medium-emphasis">{{ t('timeline.asideLabel') }}</div>
                                    <div class="font-weight-bold text-body-1 text-truncate">{{ user.name }}</div>
                                    <div v-if="user.username" class="text-caption text-medium-emphasis text-truncate">
                                        @{{ user.username }}
                                    </div>
                                </div>
                            </div>

                            <v-divider v-if="user.id" class="mb-3" />

                            <p class="text-body-2 text-medium-emphasis mb-0">
                                {{ t('timeline.asidePrompt') }}
                            </p>
                        </v-card>
                    </div>
                </v-col>

                <!-- Feed column -->
                <v-col cols="12" md="8" lg="6" class="feed-col">
                    <!-- Status Composer -->
                    <StatusComposer @status-posted="handleStatusPosted" />

                    <!-- Loading Initial -->
                    <div v-if="loading" class="text-center py-8">
                        <v-progress-circular indeterminate color="primary" size="48" />
                    </div>

                    <!-- Timeline Feed -->
                    <div v-else>
                        <!-- Status Cards -->
                        <StatusCard
                            v-for="status in statuses"
                            :key="status.id"
                            :status="status"
                            @updated="handleStatusUpdated"
                            @deleted="handleStatusDeleted"
                        />

                        <!-- Load More Button -->
                        <div v-if="hasMore && statuses.length > 0" class="text-center py-4">
                            <v-btn
                                variant="tonal"
                                color="primary"
                                block
                                @click="loadMore"
                                :loading="loadingMore"
                                append-icon="mdi-arrow-down"
                            >
                                {{ t('timeline.loadMore') }}
                            </v-btn>
                        </div>

                        <!-- End of Feed -->
                        <div v-else-if="statuses.length > 0" class="end-of-feed text-center py-6 text-medium-emphasis">
                            <v-icon size="28" color="primary" class="mb-1">mdi-check-circle-outline</v-icon>
                            <p class="text-body-2 font-weight-medium mb-0">{{ t('timeline.allCaughtUp') }}</p>
                            <p class="text-caption mb-0">{{ t('timeline.caughtUpNote') }}</p>
                        </div>

                        <!-- Empty State -->
                        <v-card v-if="statuses.length === 0" flat class="empty-state text-center pa-8">
                            <v-icon size="56" color="primary" class="mb-4 empty-icon">
                                mdi-timeline-text-outline
                            </v-icon>
                            <h3 class="text-h6 mb-2">{{ t('timeline.noStatusYet') }}</h3>
                            <p class="text-body-2 text-medium-emphasis mb-0">
                                {{ t('timeline.beFirst') }}
                            </p>
                        </v-card>
                    </div>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<style scoped>
.status-timeline-container {
    min-height: 100vh;
    background-color: rgb(var(--v-theme-background));
}

/* ---------- Header band (full width, content constrained) ---------- */
.timeline-header {
    background-color: rgb(var(--v-theme-surface));
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.timeline-header-inner {
    width: 100%;
    padding: clamp(1.5rem, 4vw, 2.75rem) 1.25rem;
}

.timeline-eyebrow {
    margin: 0 0 0.35rem;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgb(var(--v-theme-accent));
}

.timeline-title {
    position: relative;
    display: inline-block;
    line-height: 1.1;
    margin-bottom: 0.75rem;
}

/* One quiet signature: a short accent bar anchoring the title */
.timeline-title::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -0.35rem;
    width: 48px;
    height: 3px;
    border-radius: 3px;
    background: rgb(var(--v-theme-accent));
}

/* ---------- Body grid ---------- */
.timeline-body {
    max-width: 1160px;
}

/* Sticky context rail on desktop; clears a typical top app bar */
.timeline-aside {
    position: sticky;
    top: 88px;
}

.aside-card {
    background-color: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.min-w-0 {
    min-width: 0;
}

/* ---------- States ---------- */
.end-of-feed {
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    margin-top: 0.5rem;
}

.empty-state {
    background-color: rgb(var(--v-theme-surface));
    border: 1px dashed rgba(var(--v-theme-primary), 0.28);
    border-radius: 16px;
}

.empty-icon {
    opacity: 0.7;
}
</style>
