<template>
    <settings-page-layout
        :title="$t('ranks.admin.title')"
        :description="$t('ranks.admin.description')"
        icon="mdi-shield-star-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'achievements' } }"
        :show-save-button="false"
    >
        <settings-card icon="mdi-stairs" :title="$t('ranks.admin.ladder')">
            <div class="d-flex justify-space-between align-center mb-4">
                <span class="text-caption text-medium-emphasis">{{ $t('ranks.admin.hint') }}</span>
                <v-btn color="primary" variant="flat" size="small" prepend-icon="mdi-plus" @click="openEditor()">
                    {{ $t('ranks.admin.add') }}
                </v-btn>
            </div>

            <loading-state v-if="loading" compact />

            <empty-state
                v-else-if="!ranks.length"
                compact
                icon="mdi-shield-star-outline"
                :title="$t('ranks.admin.empty')"
            />

            <v-table v-else density="comfortable">
                <thead>
                    <tr>
                        <th>{{ $t('ranks.admin.table.rank') }}</th>
                        <th>{{ $t('ranks.admin.table.points') }}</th>
                        <th>{{ $t('ranks.admin.table.members') }}</th>
                        <th>{{ $t('ranks.admin.table.enabled') }}</th>
                        <th class="text-right">{{ $t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="rank in ranks" :key="rank.id">
                        <td>
                            <div class="d-flex align-center ga-2">
                                <achievement-badge :achievement="rank" :size="30" />
                                <div>
                                    <div class="font-weight-medium">{{ rank.name }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ rank.description }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ rank.points_required }}</td>
                        <td>{{ rank.members }}</td>
                        <td>
                            <v-switch
                                :model-value="rank.is_enabled"
                                density="compact"
                                hide-details
                                color="success"
                                :disabled="busyId !== null"
                                :loading="busyId === rank.id"
                                @update:model-value="toggle(rank, $event)"
                            />
                        </td>
                        <td class="text-right">
                            <v-btn icon variant="text" size="small" @click="openEditor(rank)">
                                <v-icon size="small">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn
                                icon
                                variant="text"
                                size="small"
                                color="error"
                                :loading="deletingId === rank.id"
                                @click="remove(rank)"
                            >
                                <v-icon size="small">mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </settings-card>

        <v-dialog v-model="showEditor" max-width="640" persistent scrollable>
            <v-card>
                <v-card-title class="text-h6">
                    {{ editing ? $t('ranks.admin.edit') : $t('ranks.admin.add') }}
                </v-card-title>
                <v-divider />

                <v-card-text>
                    <v-form ref="formRef" @submit.prevent="save">
                        <v-tabs v-model="activeLocale" density="compact" class="mb-3">
                            <v-tab v-for="locale in locales" :key="locale" :value="locale">
                                {{ locale.toUpperCase() }}
                            </v-tab>
                        </v-tabs>

                        <v-window v-model="activeLocale" class="mb-3">
                            <v-window-item v-for="locale in locales" :key="locale" :value="locale">
                                <v-text-field
                                    v-model="form.translations[locale].name"
                                    :label="$t('ranks.admin.form.name')"
                                    :rules="locale === defaultLocale ? [v => !!v || $t('common.required')] : []"
                                    :hint="locale === defaultLocale ? '' : $t('achievements.admin.form.fallsBack', { locale: defaultLocale.toUpperCase() })"
                                    persistent-hint
                                    density="compact"
                                    variant="outlined"
                                    class="mb-2"
                                />
                                <v-textarea
                                    v-model="form.translations[locale].description"
                                    :label="$t('ranks.admin.form.description')"
                                    rows="2"
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-window-item>
                        </v-window>

                        <div class="d-flex ga-4 align-start mb-2">
                            <div class="text-center flex-shrink-0">
                                <achievement-badge :achievement="preview" :size="56" />
                            </div>

                            <div class="flex-grow-1">
                                <v-file-input
                                    v-model="imageFile"
                                    :label="$t('ranks.admin.form.image')"
                                    :hint="$t('achievements.admin.form.badgeImageHint')"
                                    persistent-hint
                                    accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                                    prepend-icon=""
                                    prepend-inner-icon="mdi-image-outline"
                                    density="compact"
                                    variant="outlined"
                                    :loading="uploading"
                                    @update:model-value="onImageChosen"
                                />
                                <v-btn
                                    v-if="form.image_url"
                                    size="x-small"
                                    variant="text"
                                    color="error"
                                    prepend-icon="mdi-close"
                                    :loading="uploading"
                                    @click="removeImage"
                                >
                                    {{ $t('achievements.admin.form.removeImage') }}
                                </v-btn>
                            </div>
                        </div>

                        <v-row dense>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model.number="form.points_required"
                                    :label="$t('ranks.admin.form.points')"
                                    type="number"
                                    min="0"
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="form.icon"
                                    :label="$t('achievements.admin.form.icon')"
                                    :disabled="Boolean(form.image_url)"
                                    density="compact"
                                    variant="outlined"
                                    :prepend-inner-icon="form.icon || 'mdi-shield-outline'"
                                />
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-select
                                    v-model="form.color"
                                    :items="colors"
                                    :label="$t('achievements.admin.form.color')"
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>

                        <v-switch
                            v-model="form.is_enabled"
                            :label="$t('ranks.admin.form.enabled')"
                            color="success"
                            hide-details
                            density="compact"
                        />
                    </v-form>
                </v-card-text>

                <v-divider />
                <v-card-actions>
                    <v-btn variant="text" @click="showEditor = false">{{ $t('common.cancel') }}</v-btn>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" :loading="saving" @click="save">
                        {{ $t('common.save') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </settings-page-layout>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import AchievementBadge from '@/components/achievements/AchievementBadge.vue';
import { useDialog } from '@/composables/useDialog.js';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const COLORS = ['blue-grey', 'teal', 'blue', 'indigo', 'deep-purple', 'purple', 'amber', 'orange', 'red', 'green'];

const { t } = useI18n();
const dialog = useDialog();

const loading = ref(true);
const saving = ref(false);
const ranks = ref([]);
const locales = ref(['en']);
const defaultLocale = ref('en');
const activeLocale = ref('en');
const busyId = ref(null);
const deletingId = ref(null);

const showEditor = ref(false);
const editing = ref(null);
const formRef = ref(null);
const form = ref(blankForm());

const imageFile = ref(null);
const pendingImage = ref(null);
const pendingPreview = ref(null);
const uploading = ref(false);

const colors = COLORS;

function blankTranslations() {
    return Object.fromEntries(locales.value.map(locale => [locale, { name: '', description: '' }]));
}

function blankForm() {
    return {
        translations: blankTranslations(),
        points_required: 0,
        icon: 'mdi-shield-outline',
        image_url: null,
        color: 'blue-grey',
        is_enabled: true,
    };
}

const preview = computed(() => ({
    name: form.value.translations[defaultLocale.value]?.name,
    icon: form.value.icon,
    color: form.value.color,
    image_url: pendingPreview.value || form.value.image_url,
}));

function clearPendingImage() {
    if (pendingPreview.value) URL.revokeObjectURL(pendingPreview.value);

    pendingPreview.value = null;
    pendingImage.value = null;
    imageFile.value = null;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/ranks');

        ranks.value = data.data || [];
        locales.value = data.locales?.length ? data.locales : ['en'];
        defaultLocale.value = locales.value.includes('en') ? 'en' : locales.value[0];
        activeLocale.value = defaultLocale.value;
    } catch (error) {
        await dialog.requestError(error, t('ranks.admin.loadFailed'));
    } finally {
        loading.value = false;
    }
}

function openEditor(rank = null) {
    clearPendingImage();
    editing.value = rank;
    activeLocale.value = defaultLocale.value;

    if (rank) {
        const translations = blankTranslations();

        for (const [locale, wording] of Object.entries(rank.translations || {})) {
            translations[locale] = { name: wording.name || '', description: wording.description || '' };
        }

        form.value = { ...rank, translations };
    } else {
        form.value = blankForm();
    }

    showEditor.value = true;
}

/**
 * A picture needs a rank to belong to, so one chosen while creating waits
 * until the record exists.
 */
async function onImageChosen(value) {
    const file = Array.isArray(value) ? value[0] : value;

    if (!file) return;

    if (editing.value) {
        await uploadImage(editing.value.id, file);
        imageFile.value = null;
        return;
    }

    if (pendingPreview.value) URL.revokeObjectURL(pendingPreview.value);

    pendingImage.value = file;
    pendingPreview.value = URL.createObjectURL(file);
}

async function uploadImage(id, file) {
    uploading.value = true;
    try {
        const payload = new FormData();
        payload.append('image', file);

        const { data } = await axios.post(`/api/admin/ranks/${id}/image`, payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        form.value.image_url = data.data.image_url;
        clearPendingImage();
    } catch (error) {
        await dialog.requestError(error, t('ranks.admin.imageFailed'));
    } finally {
        uploading.value = false;
    }
}

async function removeImage() {
    if (!editing.value || pendingImage.value) {
        clearPendingImage();
        form.value.image_url = null;
        return;
    }

    uploading.value = true;
    try {
        await axios.delete(`/api/admin/ranks/${editing.value.id}/image`);
        form.value.image_url = null;
        clearPendingImage();
        await load();
    } catch (error) {
        await dialog.requestError(error, t('ranks.admin.imageFailed'));
    } finally {
        uploading.value = false;
    }
}

async function save() {
    if (saving.value) return;

    const { valid } = await formRef.value.validate();
    if (!valid) return;

    saving.value = true;
    try {
        if (editing.value) {
            await axios.patch(`/api/admin/ranks/${editing.value.id}`, form.value);
        } else {
            const { data } = await axios.post('/api/admin/ranks', form.value);

            if (pendingImage.value) {
                await uploadImage(data.data.id, pendingImage.value);
            }
        }

        clearPendingImage();
        showEditor.value = false;
        await load();
        await dialog.success(t('ranks.admin.saved'));
    } catch (error) {
        await dialog.requestError(error, t('ranks.admin.saveFailed'));
    } finally {
        saving.value = false;
    }
}

async function toggle(rank, enabled) {
    busyId.value = rank.id;
    try {
        await axios.patch(`/api/admin/ranks/${rank.id}`, {
            ...rank,
            translations: rank.translations,
            is_enabled: enabled,
        });
        rank.is_enabled = enabled;
    } catch (error) {
        await dialog.requestError(error, t('ranks.admin.saveFailed'));
    } finally {
        busyId.value = null;
    }
}

async function remove(rank) {
    const confirmed = await dialog.confirm({
        title: t('ranks.admin.deleteTitle'),
        message: t('ranks.admin.deleteConfirm', { name: rank.name }),
        confirmText: t('common.delete'),
        confirmColor: 'error',
    });

    if (!confirmed) return;

    deletingId.value = rank.id;
    try {
        await axios.delete(`/api/admin/ranks/${rank.id}`);
        await load();
    } catch (error) {
        await dialog.requestError(error, t('ranks.admin.deleteFailed'));
    } finally {
        deletingId.value = null;
    }
}

onMounted(load);
</script>
