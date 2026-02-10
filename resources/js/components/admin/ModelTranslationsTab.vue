<template>
    <div class="model-translations-tab">
        <v-row>
            <!-- Stats Overview -->
            <v-col cols="12">
                <v-card variant="outlined" class="mb-4">
                    <v-card-title class="d-flex align-center justify-space-between">
                        <span>{{ $t('modelTranslations.translationCoverage') }}</span>
                        <v-btn
                            variant="tonal"
                            color="primary"
                            size="small"
                            :loading="loadingStats"
                            @click="fetchStats"
                        >
                            <v-icon icon="mdi-refresh" start />
                            {{ $t('common.refresh') }}
                        </v-btn>
                    </v-card-title>
                    <v-card-text>
                        <v-row v-if="stats.overall">
                            <v-col
                                v-for="locale in locales"
                                :key="locale"
                                cols="12"
                                :md="12 / locales.length"
                            >
                                <v-card
                                    :color="getLocaleColor(stats.overall[locale]?.percentage)"
                                    variant="tonal"
                                >
                                    <v-card-text class="text-center">
                                        <div class="text-h4 font-weight-bold">
                                            {{ stats.overall[locale]?.percentage || 0 }}%
                                        </div>
                                        <div class="text-body-2 text-uppercase font-weight-medium">
                                            {{ locale }}
                                        </div>
                                        <div class="text-caption">
                                            {{ stats.overall[locale]?.translated || 0 }} / {{ stats.overall[locale]?.total || 0 }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row>
            <!-- Model List -->
            <v-col cols="12" md="4">
                <v-card variant="outlined">
                    <v-card-title>{{ $t('modelTranslations.models') }}</v-card-title>
                    <v-divider />
                    <v-list density="compact" nav>
                        <v-list-item
                            v-for="model in models"
                            :key="model.key"
                            :active="selectedModel === model.key"
                            @click="selectModel(model.key)"
                        >
                            <template #prepend>
                                <v-icon :icon="getModelIcon(model.key)" />
                            </template>
                            <v-list-item-title>{{ model.label }}</v-list-item-title>
                            <template #append>
                                <v-chip size="x-small" color="grey">
                                    {{ model.count }}
                                </v-chip>
                            </template>
                        </v-list-item>
                    </v-list>

                    <!-- Per-model stats -->
                    <v-divider v-if="selectedModel && modelStats" />
                    <v-card-text v-if="selectedModel && modelStats">
                        <div class="text-subtitle-2 mb-2">
                            {{ $t('modelTranslations.coverageByLocale') }}
                        </div>
                        <div
                            v-for="locale in locales"
                            :key="locale"
                            class="d-flex align-center justify-space-between mb-1"
                        >
                            <span class="text-caption text-uppercase">{{ locale }}</span>
                            <div class="d-flex align-center">
                                <v-progress-linear
                                    :model-value="modelStats.locales[locale]?.percentage || 0"
                                    :color="getLocaleColor(modelStats.locales[locale]?.percentage)"
                                    height="8"
                                    rounded
                                    style="width: 100px;"
                                    class="mr-2"
                                />
                                <span class="text-caption">
                                    {{ modelStats.locales[locale]?.percentage || 0 }}%
                                </span>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Items List -->
            <v-col cols="12" md="8">
                <v-card v-if="!selectedModel" variant="outlined">
                    <v-card-text class="text-center pa-8">
                        <v-icon icon="mdi-translate" size="64" color="grey" />
                        <div class="text-h6 text-grey mt-4">
                            {{ $t('modelTranslations.selectModelToManage') }}
                        </div>
                    </v-card-text>
                </v-card>

                <v-card v-else variant="outlined">
                    <v-card-title class="d-flex align-center justify-space-between">
                        <span>{{ getSelectedModelLabel() }}</span>
                        <v-text-field
                            v-model="search"
                            :placeholder="$t('common.search')"
                            prepend-inner-icon="mdi-magnify"
                            variant="outlined"
                            density="compact"
                            hide-details
                            clearable
                            style="max-width: 250px;"
                            @update:model-value="debouncedSearch"
                        />
                    </v-card-title>
                    <v-divider />

                    <v-data-table-server
                        v-model:items-per-page="itemsPerPage"
                        v-model:page="page"
                        :headers="itemHeaders"
                        :items="items"
                        :items-length="totalItems"
                        :loading="loadingItems"
                        density="compact"
                        @update:options="loadItems"
                    >
                        <template #item.identifier="{ item }">
                            <span class="font-weight-medium">{{ item.identifier }}</span>
                        </template>

                        <template #item.translation_status="{ item }">
                            <div class="d-flex ga-1">
                                <v-chip
                                    v-for="locale in locales"
                                    :key="locale"
                                    size="x-small"
                                    :color="item.translation_status[locale] ? 'success' : 'warning'"
                                    variant="flat"
                                >
                                    {{ locale.toUpperCase() }}
                                </v-chip>
                            </div>
                        </template>

                        <template #item.actions="{ item }">
                            <v-btn
                                icon
                                size="small"
                                variant="text"
                                color="primary"
                                @click="editItem(item)"
                            >
                                <v-icon icon="mdi-pencil" size="small" />
                            </v-btn>
                        </template>
                    </v-data-table-server>
                </v-card>
            </v-col>
        </v-row>

        <!-- Edit Dialog -->
        <v-dialog v-model="editDialog" max-width="900" persistent>
            <TranslationEditor
                v-if="editingItem"
                :model-type="selectedModel"
                :item-id="editingItem.id"
                :item-identifier="editingItem.identifier"
                :fields="selectedModelFields"
                :locales="locales"
                :translations="editingTranslations"
                :saving="saving"
                @save="saveTranslations"
                @cancel="closeEditDialog"
            />
        </v-dialog>

        <!-- Snackbar -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000">
            {{ snackbar.text }}
        </v-snackbar>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import TranslationEditor from './TranslationEditor.vue';

const { t, locale } = useI18n();

// State
const models = ref([]);
const locales = ref([]);
const selectedModel = ref(null);
const items = ref([]);
const totalItems = ref(0);
const search = ref('');
const page = ref(1);
const itemsPerPage = ref(25);

const stats = reactive({
    models: [],
    overall: {},
});

const loadingStats = ref(false);
const loadingItems = ref(false);
const saving = ref(false);

const editDialog = ref(false);
const editingItem = ref(null);
const editingTranslations = ref({});

const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
});

// Computed
const modelStats = computed(() => {
    return stats.models.find(m => m.key === selectedModel.value);
});

const selectedModelFields = computed(() => {
    const model = models.value.find(m => m.key === selectedModel.value);
    return model?.fields || [];
});

const itemHeaders = computed(() => [
    { title: 'ID', key: 'id', width: '80px' },
    { title: t('modelTranslations.identifier'), key: 'identifier' },
    { title: t('modelTranslations.translations'), key: 'translation_status', sortable: false },
    { title: '', key: 'actions', width: '80px', sortable: false },
]);

// Methods
const fetchModels = async () => {
    try {
        const response = await axios.get('/api/admin/model-translations');
        models.value = response.data.models;
        locales.value = response.data.locales;
    } catch (error) {
        console.error('Failed to fetch models:', error);
        showSnackbar(t('modelTranslations.failedToLoadModels'), 'error');
    }
};

const fetchStats = async () => {
    loadingStats.value = true;
    try {
        const response = await axios.get('/api/admin/model-translations/stats');
        stats.models = response.data.models;
        stats.overall = response.data.overall;
    } catch (error) {
        console.error('Failed to fetch stats:', error);
        showSnackbar(t('modelTranslations.failedToLoadStats'), 'error');
    } finally {
        loadingStats.value = false;
    }
};

const selectModel = (modelKey) => {
    selectedModel.value = modelKey;
    page.value = 1;
    search.value = '';
    loadItems();
};

const loadItems = async () => {
    if (!selectedModel.value) return;

    loadingItems.value = true;
    try {
        const response = await axios.get(`/api/admin/model-translations/${selectedModel.value}`, {
            params: {
                page: page.value,
                per_page: itemsPerPage.value,
                search: search.value,
                locale: locale.value,
            },
        });
        items.value = response.data.items.data;
        totalItems.value = response.data.items.total;
    } catch (error) {
        console.error('Failed to load items:', error);
        showSnackbar(t('modelTranslations.failedToLoadItems'), 'error');
    } finally {
        loadingItems.value = false;
    }
};

let searchTimeout = null;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        page.value = 1;
        loadItems();
    }, 300);
};

const editItem = async (item) => {
    editingItem.value = item;
    try {
        const response = await axios.get(`/api/admin/model-translations/${selectedModel.value}/${item.id}`);
        editingTranslations.value = response.data.translations;
        editDialog.value = true;
    } catch (error) {
        console.error('Failed to load translations:', error);
        showSnackbar(t('modelTranslations.failedToLoadTranslations'), 'error');
    }
};

const saveTranslations = async (translations) => {
    saving.value = true;
    try {
        await axios.put(`/api/admin/model-translations/${selectedModel.value}/${editingItem.value.id}`, {
            translations,
        });
        showSnackbar(t('modelTranslations.translationsSaved'));
        closeEditDialog();
        loadItems();
        fetchStats();
    } catch (error) {
        console.error('Failed to save translations:', error);
        showSnackbar(t('modelTranslations.failedToSaveTranslations'), 'error');
    } finally {
        saving.value = false;
    }
};

const closeEditDialog = () => {
    editDialog.value = false;
    editingItem.value = null;
    editingTranslations.value = {};
};

const getSelectedModelLabel = () => {
    const model = models.value.find(m => m.key === selectedModel.value);
    return model?.label || selectedModel.value;
};

const getModelIcon = (modelKey) => {
    const icons = {
        page: 'mdi-file-document',
        post: 'mdi-post',
        wiki: 'mdi-wikipedia',
        event: 'mdi-calendar',
        collection: 'mdi-folder-multiple-image',
        term: 'mdi-tag',
        taxonomy: 'mdi-tag-multiple',
        section: 'mdi-view-dashboard',
        widget: 'mdi-widgets',
        homepage_menu_item: 'mdi-menu',
    };
    return icons[modelKey] || 'mdi-database';
};

const getLocaleColor = (percentage) => {
    if (percentage >= 90) return 'success';
    if (percentage >= 50) return 'warning';
    return 'error';
};

const showSnackbar = (text, color = 'success') => {
    snackbar.text = text;
    snackbar.color = color;
    snackbar.show = true;
};

// Watch for locale changes to refresh data
watch(locale, () => {
    // Refresh items when locale changes
    if (selectedModel.value) {
        loadItems();
    }
    // Refresh stats when locale changes
    fetchStats();
});

// Lifecycle
onMounted(async () => {
    await fetchModels();
    await fetchStats();
});
</script>

<style scoped>
.model-translations-tab :deep(.v-data-table) {
    font-size: 14px;
}

.ga-1 {
    gap: 4px;
}
</style>
