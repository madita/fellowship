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
        <div class="timeline-header pa-4">
            <h1 class="text-h4 font-weight-bold mb-2">Timeline</h1>
            <p class="text-subtitle-1 text-medium-emphasis">
                Share your thoughts with the community
            </p>
        </div>

        <v-container class="py-4" fluid>
            <v-row>
                <!-- Main Feed Column -->
                <v-col cols="12" md="8" lg="9">
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
                        <div v-if="hasMore" class="text-center py-4">
                            <v-btn
                                variant="outlined"
                                color="primary"
                                @click="loadMore"
                                :loading="loadingMore"
                                prepend-icon="mdi-refresh"
                            >
                                Load More
                            </v-btn>
                        </div>

                        <!-- End of Feed -->
                        <div v-else-if="statuses.length > 0" class="text-center py-4 text-medium-emphasis">
                            <v-icon size="32" class="mb-2">mdi-check-circle-outline</v-icon>
                            <p class="text-caption">You're all caught up!</p>
                        </div>

                        <!-- Empty State -->
                        <v-card v-if="statuses.length === 0" flat class="text-center pa-8">
                            <v-icon size="64" color="grey-lighten-2" class="mb-4">
                                mdi-timeline-text-outline
                            </v-icon>
                            <h3 class="text-h6 mb-2">No status updates yet</h3>
                            <p class="text-body-2 text-medium-emphasis mb-4">
                                Be the first to share something!
                            </p>
                        </v-card>
                    </div>
                </v-col>

                <!-- Sidebar Column -->
                <v-col cols="12" md="4" lg="3" class="d-none d-md-block">
                    <v-card elevation="2" rounded="lg" class="pa-4">
                        <div class="d-flex align-center mb-3">
                            <UserAvatar :user="user" size="48" class="mr-3" />
                            <div>
                                <div class="font-weight-medium">{{ user.name }}</div>
                                <div v-if="user.username" class="text-caption text-medium-emphasis">
                                    @{{ user.username }}
                                </div>
                            </div>
                        </div>
                        <v-divider class="mb-3" />
                        <div class="text-caption text-medium-emphasis">
                            Welcome to the timeline. Share your thoughts with the community!
                        </div>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<style scoped>
.status-timeline-container {
    min-height: 100vh;
    background-color: rgb(var(--v-theme-surface));
}

.timeline-header {
    background-color: rgb(var(--v-theme-surface));
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
</style>
