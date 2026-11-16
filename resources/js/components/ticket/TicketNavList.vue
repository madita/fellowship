<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import EmptyState from '@/components/common/EmptyState.vue';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';
import { ticketFiltersFromQuery, ticketFilterParams } from '@/composables/useTicketListQuery.js';

/**
 * Tickets of the list the ticket page was opened from (same filters), to
 * jump between tickets without going back. Lives in the ticket drawer.
 */
const props = defineProps({
    // Address query of that list
    listQuery: { type: Object, default: () => ({}) },
    detailRouteName: { type: String, required: true },
    currentId: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const { t } = useI18n();
const { getStatusColor, getStatusLabel, getPriorityColor, getPriorityIcon, getPriorityLabel } = useTicketHelpers();

const PER_PAGE = 30;

const listRef = ref(null);
const items = ref([]);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const loadFailed = ref(false);

const baseFilters = computed(() => ticketFiltersFromQuery(props.listQuery));
const search = ref(baseFilters.value.search);

let requestId = 0;

const load = async ({ more = false } = {}) => {
    const id = ++requestId;
    loading.value = true;
    loadFailed.value = false;
    try {
        const params = {
            ...ticketFilterParams({ ...baseFilters.value, search: search.value || '' }),
            per_page: PER_PAGE,
            page: more ? page.value + 1 : 1,
        };
        const response = await axios.get('/api/tickets', { params });
        if (id !== requestId) return;
        items.value = more ? [...items.value, ...response.data.data] : response.data.data;
        page.value = response.data.current_page;
        lastPage.value = response.data.last_page;
        total.value = response.data.total;
        if (!more) scrollToCurrent();
    } catch (err) {
        if (id !== requestId) return;
        console.error('Failed to load ticket navigation:', err);
        loadFailed.value = true;
    } finally {
        if (id === requestId) loading.value = false;
    }
};

const onSearch = useDebounceFn(() => load(), 300);

const scrollToCurrent = async () => {
    await nextTick();
    listRef.value?.$el?.querySelector('.v-list-item--active')?.scrollIntoView({ block: 'nearest' });
};

// Keep the entry in step when the ticket is changed on the page
const updateItem = (ticket) => {
    const index = items.value.findIndex(item => item.id === ticket.id);
    if (index !== -1) {
        items.value[index] = { ...items.value[index], title: ticket.title, status: ticket.status, priority: ticket.priority };
    }
};

defineExpose({ reload: load, updateItem });

onMounted(load);
</script>

<template>
    <div class="ticket-nav d-flex flex-column fill-height">
        <div class="pa-3 flex-shrink-0">
            <div class="d-flex align-center mb-2">
                <span class="text-subtitle-2 font-weight-medium">{{ t('tickets.nav.title') }}</span>
                <span v-if="total" class="text-caption text-medium-emphasis ml-2">{{ total }}</span>
                <v-spacer />
                <v-btn
                    icon="mdi-chevron-double-left"
                    size="small"
                    variant="text"
                    density="comfortable"
                    :title="t('tickets.nav.hide')"
                    :aria-label="t('tickets.nav.hide')"
                    @click="emit('close')"
                />
            </div>
            <v-text-field
                v-model="search"
                :placeholder="t('tickets.filters.searchPlaceholder')"
                prepend-inner-icon="mdi-magnify"
                density="compact"
                hide-details
                clearable
                @update:model-value="onSearch"
            />
        </div>

        <v-progress-linear :active="loading" indeterminate color="primary" height="2" class="flex-shrink-0" />
        <v-divider />

        <div class="flex-grow-1 overflow-y-auto">
            <v-list v-if="items.length" ref="listRef" density="compact" nav class="py-1">
                <v-list-item
                    v-for="item in items"
                    :key="item.id"
                    :to="{ name: detailRouteName, params: { id: item.id } }"
                    :active="item.id === currentId"
                    color="primary"
                    rounded="lg"
                    class="mb-1"
                >
                    <template #prepend>
                        <v-icon
                            size="small"
                            :color="getPriorityColor(item.priority)"
                            :icon="getPriorityIcon(item.priority)"
                            :title="getPriorityLabel(item.priority)"
                            class="mr-n2"
                        />
                    </template>
                    <v-list-item-title class="text-body-2">{{ item.title }}</v-list-item-title>
                    <v-list-item-subtitle class="d-flex align-center ga-1 text-caption">
                        <v-icon size="8" :color="getStatusColor(item.status)" icon="mdi-circle" />
                        {{ getStatusLabel(item.status) }} · #{{ item.id }}
                    </v-list-item-subtitle>
                </v-list-item>

                <div v-if="page < lastPage" class="px-2 py-1">
                    <v-btn block variant="text" size="small" :loading="loading" @click="load({ more: true })">
                        {{ t('tickets.nav.loadMore') }}
                    </v-btn>
                </div>
            </v-list>

            <empty-state
                v-else-if="!loading"
                compact
                icon="mdi-ticket-outline"
                :title="loadFailed ? t('tickets.messages.loadFailed') : t('tickets.noTickets')"
            >
                <template v-if="loadFailed" #actions>
                    <v-btn variant="tonal" size="small" @click="load()">{{ t('tickets.nav.retry') }}</v-btn>
                </template>
            </empty-state>
        </div>
    </div>
</template>
