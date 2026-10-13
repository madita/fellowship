<template>
    <div>
        <loading-state v-if="loading && !rows.length" />

        <empty-state
            v-else-if="!rows.length"
            icon="mdi-pulse"
            :title="$t('activity.empty')"
            :text="$t('activity.emptyHint')"
        />

        <v-list v-else lines="two" class="pa-0">
            <template v-for="(row, index) in rows" :key="row.id">
                <v-divider v-if="index > 0" />
                <v-list-item :to="row.url || undefined">
                    <template #prepend>
                        <user-avatar
                            v-if="row.causer"
                            :user="row.causer"
                            size="32"
                            class="mr-1"
                        />
                        <v-avatar v-else size="32" color="surface-variant" class="mr-1">
                            <v-icon size="18">{{ activityIcon(row) }}</v-icon>
                        </v-avatar>
                    </template>

                    <v-list-item-title class="text-body-2">
                        <strong v-if="row.causer">{{ row.causer.username }}</strong>
                        {{ activityText(row) }}
                    </v-list-item-title>

                    <v-list-item-subtitle class="text-caption">
                        <v-icon size="12" class="mr-1">{{ activityIcon(row) }}</v-icon>
                        {{ $formatDistanceToNow(row.created_at) }}
                    </v-list-item-subtitle>
                </v-list-item>
            </template>
        </v-list>

        <div v-if="meta.has_more" class="d-flex justify-center mt-3">
            <v-btn variant="text" :loading="loading" @click="loadMore">
                {{ $t('activity.loadMore') }}
            </v-btn>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import LoadingState from '@/components/common/LoadingState.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import UserAvatar from '@/components/common/UserAvatar.vue';
import { useDialog } from '@/composables/useDialog.js';
import { useActivityText } from '@/composables/useActivityText.js';

/**
 * What has been happening, for one feature or across the site.
 *
 * Every entry carries its own title and url — see App\Support\Activities —
 * so this renders any feature without knowing anything about it.
 */
const props = defineProps({
    // One of the log names in App\Support\Activities; omit for everything
    log: { type: String, default: null },
    // Narrow to one thing's history: a short subject name from
    // App\Support\Activities::subjects(), e.g. 'wiki' or 'ticket'
    subject: { type: String, default: null },
    subjectId: { type: [Number, String], default: null },
    causerId: { type: [Number, String], default: null },
    perPage: { type: Number, default: 20 },
});

const { t } = useI18n();
const dialog = useDialog();
const { activityText, activityIcon } = useActivityText();

const loading = ref(true);
const rows = ref([]);
const meta = ref({ current_page: 1, has_more: false });

const load = async (page = 1) => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/activity', {
            params: {
                log: props.log || undefined,
                subject: props.subject || undefined,
                subject_id: props.subjectId || undefined,
                causer_id: props.causerId || undefined,
                per_page: props.perPage,
                page,
            },
        });

        rows.value = page === 1 ? data.data : [...rows.value, ...data.data];
        meta.value = data.meta;
    } catch (error) {
        await dialog.requestError(error, t('activity.loadFailed'));
    } finally {
        loading.value = false;
    }
};

const loadMore = () => load(meta.value.current_page + 1);

watch(() => [props.log, props.subject, props.subjectId, props.causerId], () => load(1));
onMounted(() => load(1));
</script>
