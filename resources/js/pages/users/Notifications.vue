<template>
    <div class="d-flex flex-column flex-grow-1">
        <page-header
            :title="$t('notifications.title')"
            :subtitle="$t('notifications.subtitle')"
            icon="mdi-bell-outline"
        />

        <v-container>
            <loading-state v-if="isLoading && !hasNotifications" />

            <empty-state
                v-else-if="!hasNotifications"
                icon="mdi-bell-off-outline"
                :title="$t('notifications.noNotifications')"
                :text="$t('notifications.emptyHint')"
            />

            <template v-else>
                <v-card
                    v-for="(item) in notifications"
                    :key="item.id"
                    class="mb-3"
                    rounded="lg"
                >
                    <v-card-title class="text-subtitle-1 font-weight-medium">{{ item.data.subject }}</v-card-title>
                    <v-card-text class="text-body-1 py-2" v-html="item.data.body"></v-card-text>
                    <v-card-text v-if="item.data.url && item.data.action" class="pt-0">
                        <v-btn :href="item.data.url" target="_blank" variant="tonal" size="small">{{ item.data.action }}</v-btn>
                    </v-card-text>

                    <v-divider />

                    <v-list-item>
                        <template v-slot:prepend>
                            <user-avatar :user="item.data.notifier"></user-avatar>
                        </template>

                        <v-list-item-title>{{ item.data.notifier.username }}</v-list-item-title>

                        <v-list-item-subtitle>{{ $formatDistanceToNow(item.created_at) }}</v-list-item-subtitle>

                        <template v-slot:append>
                            <v-btn
                                icon="mdi-delete"
                                variant="text"
                                color="error"
                                size="small"
                                :aria-label="$t('common.delete')"
                                :title="$t('common.delete')"
                                :loading="deleting.includes(item.id)"
                                :disabled="deleting.length > 0 && !deleting.includes(item.id)"
                                @click="deleteNotification(item.id)"
                            />
                        </template>
                    </v-list-item>
                </v-card>
            </template>
        </v-container>
    </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import UserAvatar from "@/components/common/UserAvatar.vue";
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import { useDialog } from '@/composables/useDialog.js';

export default {
    components: { UserAvatar, PageHeader, EmptyState, LoadingState },
    props: {
        id: {
            required: false,
            type: [String, Number]
        }
    },
    setup(props) {
        const notificationModel = ref(null);
        const isLoading = ref(false);
        const notifications = ref({});
        // Ids of notifications with a delete request in flight
        const deleting = ref([]);
        const route = useRoute();
        const { t } = useI18n();
        const dialog = useDialog();

        const hasNotifications = computed(() => {
            const list = notifications.value;
            if (!list) return false;
            return Array.isArray(list) ? list.length > 0 : Object.keys(list).length > 0;
        });

        // Watch for changes in route params
        watch(() => route.params.id, (newId) => {
            notificationModel.value = newId;
        });

        // Load all notifications on mount
        onMounted(() => {
            if (route.params.id) {
                notificationModel.value = route.params.id;
            }
            getAllNotifications();
        });

        const getAllNotifications = async () => {
            isLoading.value = true;
            try {
                const { data } = await axios.get('/api/account/notifications');
                notifications.value = data;
            } catch (err) {
                if (err.response?.status === 404) {
                    console.warn(new Error(`${err.config.url} not found`));
                } else {
                    console.warn(err);
                }
            } finally {
                isLoading.value = false;
            }
        };

        const deleteNotification = async (id) => {
            if (deleting.value.length) return;
            if (!(await dialog.confirmDelete(t('notifications.confirmDelete')))) return;

            deleting.value.push(id);
            try {
                await axios.delete('/api/account/notification/delete/' + id);
                await getAllNotifications();
            } catch (error) {
                console.warn(error);
                await dialog.requestError(error, t('notifications.deleteFailed'));
            } finally {
                deleting.value = deleting.value.filter(d => d !== id);
            }
        };

        return {
            notificationModel,
            isLoading,
            notifications,
            hasNotifications,
            deleting,
            getAllNotifications,
            deleteNotification
        };
    }
}
</script>
