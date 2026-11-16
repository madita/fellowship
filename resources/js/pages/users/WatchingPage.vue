<template>
    <div class="flex-grow-1">
        <page-header
            :title="$t('watch.title')"
            :subtitle="$t('watch.subtitle')"
            icon="mdi-eye-check-outline"
        >
            <template v-if="kinds.length > 1" #actions>
                <v-btn-toggle
                    v-model="kind"
                    color="primary"
                    rounded="lg"
                    density="comfortable"
                    mandatory
                >
                    <v-btn value="">{{ $t('watch.all') }}</v-btn>
                    <v-btn v-for="name in kinds" :key="name" :value="name">
                        {{ $t(`watch.kinds.${name}`) }}
                    </v-btn>
                </v-btn-toggle>
            </template>
        </page-header>

        <loading-state v-if="loading" />

        <empty-state
            v-else-if="!items.length"
            icon="mdi-eye-off-outline"
            :title="$t('watch.empty')"
            :text="$t('watch.emptyHint')"
        />

        <v-container v-else>
            <v-card rounded="lg" variant="elevated">
                <v-list lines="two">
                    <template v-for="(item, index) in items" :key="item.id">
                        <v-divider v-if="index > 0" />
                        <v-list-item
                            :to="item.url || undefined"
                            :prepend-icon="item.icon"
                        >
                            <v-list-item-title>{{ item.title || $t('watch.kinds.' + item.kind) }}</v-list-item-title>
                            <v-list-item-subtitle>
                                {{ $t(`watch.kinds.${item.kind}`) }}
                                · {{ $formatDistanceToNow(item.watched_at) }}
                            </v-list-item-subtitle>

                            <template #append>
                                <!-- Stop watching without opening the thing
                                     first, which is the point of a list -->
                                <v-btn
                                    icon="mdi-eye-off-outline"
                                    variant="text"
                                    density="comfortable"
                                    :loading="stopping === item.id"
                                    :title="$t('watch.stop')"
                                    @click.prevent.stop="stopWatching(item)"
                                />
                            </template>
                        </v-list-item>
                    </template>
                </v-list>
            </v-card>
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch as watchRef } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import { useDialog } from '@/composables/useDialog.js';

/**
 * Everything the member is following, in one place.
 *
 * This is what a shared watchable buys over the two features that came
 * before it: neither the forum nor the tickets could answer "what am I
 * following?", because each only knew about its own kind.
 */
const { t } = useI18n();
const dialog = useDialog();

const loading = ref(true);
const rows = ref([]);
const kinds = ref([]);
const kind = ref('');
const stopping = ref(null);

const items = computed(() => rows.value);

const load = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/watching', {
            params: kind.value ? { kind: kind.value } : {},
        });

        rows.value = data.data ?? [];
        kinds.value = data.kinds ?? [];
    } catch (error) {
        await dialog.requestError(error, t('watch.failed'));
    } finally {
        loading.value = false;
    }
};

const stopWatching = async (item) => {
    if (stopping.value) return;

    stopping.value = item.id;
    try {
        await axios.post(`/api/watch/${item.kind}/${item.watchable_id}`);

        // Taken off the list rather than reloaded: the row is gone either
        // way, and a reload would lose the member's place
        rows.value = rows.value.filter(row => row.id !== item.id);
    } catch (error) {
        await dialog.requestError(error, t('watch.failed'));
    } finally {
        stopping.value = null;
    }
};

watchRef(kind, load);
onMounted(load);
</script>
