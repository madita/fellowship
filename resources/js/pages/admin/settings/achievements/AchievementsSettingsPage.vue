<template>
    <settings-page-layout
        :title="$t('achievements.admin.title')"
        :description="$t('achievements.admin.description')"
        icon="mdi-trophy-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'achievements' } }"
        :show-save-button="false"
    >
        <!-- How the achievements are doing -->
        <settings-card icon="mdi-chart-box-outline" :title="$t('achievements.admin.stats')">
            <v-row dense>
                <v-col v-for="stat in statCards" :key="stat.key" cols="6" md="3">
                    <v-card variant="tonal" :color="stat.color" class="pa-3 text-center">
                        <div class="text-h5 font-weight-bold">{{ stat.value }}</div>
                        <div class="text-caption">{{ $t(`achievements.admin.stat.${stat.key}`) }}</div>
                    </v-card>
                </v-col>
            </v-row>

            <v-alert
                v-if="stats.unearned?.length"
                type="info"
                variant="tonal"
                density="compact"
                class="mt-3"
            >
                {{ $t('achievements.admin.unearned', { list: stats.unearned.join(', ') }) }}
            </v-alert>

            <div v-if="stats.top_members?.length" class="mt-4">
                <div class="text-caption text-medium-emphasis mb-1">{{ $t('achievements.admin.topMembers') }}</div>
                <v-chip
                    v-for="member in stats.top_members"
                    :key="member.id"
                    size="small"
                    variant="tonal"
                    class="mr-1 mb-1"
                >
                    {{ member.username }} · {{ member.points }}
                </v-chip>
            </div>
        </settings-card>

        <settings-card icon="mdi-trophy-outline" :title="$t('achievements.admin.list')">
            <div class="d-flex justify-space-between align-center mb-4">
                <span class="text-caption text-medium-emphasis">{{ $t('achievements.admin.hint') }}</span>
                <v-btn color="primary" variant="flat" size="small" prepend-icon="mdi-plus" @click="openEditor()">
                    {{ $t('achievements.admin.add') }}
                </v-btn>
            </div>

            <loading-state v-if="loading" compact />

            <empty-state
                v-else-if="!achievements.length"
                compact
                icon="mdi-trophy-outline"
                :title="$t('achievements.admin.empty')"
            />

            <v-table v-else density="comfortable">
                <thead>
                    <tr>
                        <th>{{ $t('achievements.admin.table.achievement') }}</th>
                        <th>{{ $t('achievements.admin.table.earnedBy') }}</th>
                        <th>{{ $t('achievements.admin.table.holders') }}</th>
                        <th>{{ $t('achievements.admin.table.enabled') }}</th>
                        <th class="text-right">{{ $t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="achievement in achievements" :key="achievement.id">
                        <td>
                            <div class="d-flex align-center ga-2">
                                <achievement-badge :achievement="achievement" :size="30" />
                                <div>
                                    <div class="font-weight-medium">
                                        {{ achievement.name }}
                                        <v-icon
                                            v-if="achievement.is_secret"
                                            icon="mdi-eye-off-outline"
                                            size="x-small"
                                            class="ml-1 text-medium-emphasis"
                                        />
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ achievement.points }} {{ $t('achievements.points') }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="text-caption">
                            <template v-if="achievement.trigger === 'manual'">
                                <v-icon icon="mdi-hand-heart-outline" size="x-small" class="mr-1" />
                                {{ $t('achievements.awardedByHand') }}
                            </template>
                            <template v-else>
                                <!-- An achievement watching an action the site
                                     no longer counts can never be earned -->
                                <v-chip
                                    v-if="!achievement.metric_is_known"
                                    size="x-small"
                                    color="error"
                                    variant="tonal"
                                >
                                    {{ $t('achievements.admin.unknownMetric') }}
                                </v-chip>
                                <template v-else>
                                    {{ $t(`achievements.metrics.${achievement.metric}`, achievement.metric) }}
                                    × {{ achievement.threshold }}
                                    <span v-if="achievement.filters?.values?.length" class="text-medium-emphasis">
                                        ({{ narrowedLabel(achievement) }})
                                    </span>
                                </template>
                            </template>
                        </td>

                        <td>{{ achievement.holders_count }}</td>

                        <td>
                            <v-switch
                                :model-value="achievement.is_enabled"
                                density="compact"
                                hide-details
                                color="success"
                                :disabled="busyId !== null"
                                :loading="busyId === achievement.id"
                                @update:model-value="toggle(achievement, $event)"
                            />
                        </td>

                        <td class="text-right">
                            <v-btn icon variant="text" size="small" :title="$t('achievements.admin.award')" @click="openAward(achievement)">
                                <v-icon size="small">mdi-hand-heart-outline</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" size="small" @click="openEditor(achievement)">
                                <v-icon size="small">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn
                                icon
                                variant="text"
                                size="small"
                                color="error"
                                :loading="deletingId === achievement.id"
                                @click="remove(achievement)"
                            >
                                <v-icon size="small">mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </settings-card>

        <!-- Editor -->
        <v-dialog v-model="showEditor" max-width="720" persistent scrollable>
            <v-card>
                <v-card-title class="text-h6">
                    {{ editing ? $t('achievements.admin.edit') : $t('achievements.admin.add') }}
                </v-card-title>
                <v-divider />

                <v-card-text>
                    <v-form ref="formRef" @submit.prevent="save">
                        <v-row dense>
                            <v-col cols="12" sm="8">
                                <v-text-field
                                    v-model="form.name"
                                    :label="$t('achievements.admin.form.name')"
                                    :rules="[v => !!v || $t('common.required')]"
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model.number="form.points"
                                    :label="$t('achievements.admin.form.points')"
                                    type="number"
                                    min="0"
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>

                        <v-textarea
                            v-model="form.description"
                            :label="$t('achievements.admin.form.description')"
                            rows="2"
                            density="compact"
                            variant="outlined"
                            class="mb-2"
                        />

                        <!-- How the badge looks: a picture of its own, or
                             one of the built-in icons in a colour -->
                        <div class="d-flex ga-4 align-start mb-2">
                            <div class="text-center flex-shrink-0">
                                <achievement-badge :achievement="badgePreview" :size="64" />
                                <div class="text-caption text-medium-emphasis mt-1">
                                    {{ $t('achievements.admin.form.preview') }}
                                </div>
                            </div>

                            <div class="flex-grow-1">
                                <v-file-input
                                    v-model="badgeFile"
                                    :label="$t('achievements.admin.form.badgeImage')"
                                    :hint="$t('achievements.admin.form.badgeImageHint')"
                                    persistent-hint
                                    accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                                    prepend-icon=""
                                    prepend-inner-icon="mdi-image-outline"
                                    density="compact"
                                    variant="outlined"
                                    show-size
                                    :loading="uploadingBadge"
                                    @update:model-value="onBadgeChosen"
                                />

                                <v-btn
                                    v-if="form.image_url"
                                    size="x-small"
                                    variant="text"
                                    color="error"
                                    prepend-icon="mdi-close"
                                    :loading="uploadingBadge"
                                    @click="removeBadge"
                                >
                                    {{ $t('achievements.admin.form.removeImage') }}
                                </v-btn>
                            </div>
                        </div>

                        <v-row dense>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="form.icon"
                                    :label="$t('achievements.admin.form.icon')"
                                    :disabled="Boolean(form.image_url)"
                                    :hint="form.image_url ? $t('achievements.admin.form.iconUnused') : ''"
                                    persistent-hint
                                    density="compact"
                                    variant="outlined"
                                    :prepend-inner-icon="form.icon || 'mdi-trophy-outline'"
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
                            <v-col cols="12" sm="4">
                                <v-select
                                    v-model="form.category"
                                    :items="categoryItems"
                                    :label="$t('achievements.admin.form.category')"
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>

                        <v-divider class="my-3" />

                        <!-- How it is earned -->
                        <v-btn-toggle v-model="form.trigger" mandatory density="compact" class="mb-3">
                            <v-btn value="metric" size="small">{{ $t('achievements.admin.form.automatic') }}</v-btn>
                            <v-btn value="manual" size="small">{{ $t('achievements.admin.form.byHand') }}</v-btn>
                        </v-btn-toggle>

                        <template v-if="form.trigger === 'metric'">
                            <v-row dense>
                                <v-col cols="12" sm="8">
                                    <v-select
                                        v-model="form.metric"
                                        :items="metricItems"
                                        :label="$t('achievements.admin.form.action')"
                                        :rules="[v => !!v || $t('common.required')]"
                                        density="compact"
                                        variant="outlined"
                                    />
                                </v-col>
                                <v-col cols="12" sm="4">
                                    <v-text-field
                                        v-model.number="form.threshold"
                                        :label="$t('achievements.admin.form.count')"
                                        type="number"
                                        min="1"
                                        density="compact"
                                        variant="outlined"
                                    />
                                </v-col>
                            </v-row>

                            <!-- Only actions that can be narrowed offer this:
                                 not every event is the same amount of work -->
                            <div v-if="narrowChoices.length">
                                <div class="text-caption text-medium-emphasis mb-1">
                                    {{ $t('achievements.admin.form.onlyFor') }}
                                </div>
                                <v-chip-group v-model="form.filters.values" multiple column>
                                    <v-chip
                                        v-for="choice in narrowChoices"
                                        :key="choice.value"
                                        :value="choice.value"
                                        size="small"
                                        filter
                                    >
                                        {{ choice.label }}
                                    </v-chip>
                                </v-chip-group>
                                <p class="text-caption text-medium-emphasis">
                                    {{ $t('achievements.admin.form.onlyForHint') }}
                                </p>
                            </div>
                        </template>

                        <v-alert v-else type="info" variant="tonal" density="compact" class="mb-2">
                            {{ $t('achievements.admin.form.byHandHint') }}
                        </v-alert>

                        <v-switch
                            v-model="form.is_enabled"
                            :label="$t('achievements.admin.form.enabled')"
                            color="success"
                            hide-details
                            density="compact"
                        />
                        <v-switch
                            v-model="form.is_secret"
                            :label="$t('achievements.admin.form.secret')"
                            :hint="$t('achievements.admin.form.secretHint')"
                            persistent-hint
                            color="primary"
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

        <!-- Handing one out -->
        <v-dialog v-model="showAward" max-width="520">
            <v-card v-if="awarding">
                <v-card-title class="text-h6">
                    {{ $t('achievements.admin.awardTitle', { name: awarding.name }) }}
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-autocomplete
                        v-model="awardForm.user_id"
                        :items="userResults"
                        :loading="searchingUsers"
                        item-title="username"
                        item-value="id"
                        :label="$t('achievements.admin.awardTo')"
                        :no-data-text="$t('achievements.admin.searchMembers')"
                        density="compact"
                        variant="outlined"
                        @update:search="searchUsers"
                    />
                    <v-textarea
                        v-model="awardForm.note"
                        :label="$t('achievements.admin.awardNote')"
                        :hint="$t('achievements.admin.awardNoteHint')"
                        persistent-hint
                        rows="2"
                        density="compact"
                        variant="outlined"
                    />

                    <div v-if="holders.length" class="mt-4">
                        <div class="text-caption text-medium-emphasis mb-1">
                            {{ $t('achievements.admin.currentHolders') }}
                        </div>
                        <v-chip
                            v-for="holder in holders"
                            :key="holder.id"
                            size="small"
                            closable
                            class="mr-1 mb-1"
                            @click:close="revoke(holder)"
                        >
                            {{ holder.username }}
                        </v-chip>
                    </div>
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-btn variant="text" @click="showAward = false">{{ $t('common.cancel') }}</v-btn>
                    <v-spacer />
                    <v-btn
                        color="primary"
                        variant="flat"
                        :disabled="!awardForm.user_id"
                        :loading="awardingBusy"
                        @click="award"
                    >
                        {{ $t('achievements.admin.award') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </settings-page-layout>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue';
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

const COLORS = ['amber', 'red', 'pink', 'purple', 'deep-purple', 'indigo', 'blue', 'teal', 'green', 'orange', 'brown', 'blue-grey'];

const { t } = useI18n();
const dialog = useDialog();

const loading = ref(true);
const saving = ref(false);
const achievements = ref([]);
const metrics = ref([]);
const categories = ref([]);
const stats = ref({});
const busyId = ref(null);
const deletingId = ref(null);

const showEditor = ref(false);
const editing = ref(null);
const formRef = ref(null);
const form = ref(blankForm());

// A badge picture needs an achievement to belong to, so one chosen while
// creating is held back and uploaded as soon as the record exists.
const badgeFile = ref(null);
const pendingBadge = ref(null);
const pendingPreview = ref(null);
const uploadingBadge = ref(false);

const showAward = ref(false);
const awarding = ref(null);
const awardingBusy = ref(false);
const awardForm = ref({ user_id: null, note: '' });
const holders = ref([]);
const userResults = ref([]);
const searchingUsers = ref(false);

const colors = COLORS;

function blankForm() {
    return {
        name: '',
        description: '',
        icon: 'mdi-trophy-outline',
        image_url: null,
        color: 'amber',
        category: 'community',
        points: 10,
        trigger: 'metric',
        metric: null,
        threshold: 1,
        filters: { values: [] },
        is_enabled: true,
        is_secret: false,
    };
}

const categoryItems = computed(() => categories.value.map(value => ({
    value,
    title: t(`achievements.categories.${value}`, value),
})));

const metricItems = computed(() => metrics.value.map(metric => ({
    value: metric.key,
    title: t(`achievements.metrics.${metric.key}`, metric.key),
    props: { subtitle: t(`achievements.metricGroups.${metric.group}`, metric.group) },
})));

// What the chosen action can be narrowed by, if anything
const narrowChoices = computed(() => {
    const metric = metrics.value.find(m => m.key === form.value.metric);

    return metric?.choices || [];
});

// Changing to an action that cannot be narrowed drops a stale narrowing
watch(() => form.value.metric, () => {
    if (!narrowChoices.value.length) form.value.filters = { values: [] };
});

// What the badge will look like, including a file chosen but not yet saved
const badgePreview = computed(() => ({
    name: form.value.name,
    icon: form.value.icon,
    color: form.value.color,
    image_url: pendingPreview.value || form.value.image_url,
}));

function clearPendingBadge() {
    if (pendingPreview.value) URL.revokeObjectURL(pendingPreview.value);

    pendingPreview.value = null;
    pendingBadge.value = null;
    badgeFile.value = null;
}

/**
 * An existing achievement takes the picture straight away; a new one holds
 * it until the record has been created.
 */
async function onBadgeChosen(value) {
    const file = Array.isArray(value) ? value[0] : value;

    if (!file) return;

    if (editing.value) {
        await uploadBadge(editing.value.id, file);
        badgeFile.value = null;
        return;
    }

    if (pendingPreview.value) URL.revokeObjectURL(pendingPreview.value);

    pendingBadge.value = file;
    pendingPreview.value = URL.createObjectURL(file);
}

async function uploadBadge(id, file) {
    uploadingBadge.value = true;
    try {
        const payload = new FormData();
        payload.append('image', file);

        const { data } = await axios.post(`/api/admin/achievements/${id}/badge`, payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        form.value.image_url = data.data.image_url;
        clearPendingBadge();

        return true;
    } catch (error) {
        await dialog.requestError(error, t('achievements.admin.badgeFailed'));

        return false;
    } finally {
        uploadingBadge.value = false;
    }
}

async function removeBadge() {
    // Nothing saved yet — just drop what was chosen
    if (!editing.value || pendingBadge.value) {
        clearPendingBadge();
        form.value.image_url = null;
        return;
    }

    uploadingBadge.value = true;
    try {
        await axios.delete(`/api/admin/achievements/${editing.value.id}/badge`);
        form.value.image_url = null;
        clearPendingBadge();
        await load();
    } catch (error) {
        await dialog.requestError(error, t('achievements.admin.badgeFailed'));
    } finally {
        uploadingBadge.value = false;
    }
}

const statCards = computed(() => [
    { key: 'achievements', value: stats.value.achievements ?? 0, color: 'primary' },
    { key: 'awarded', value: stats.value.awarded ?? 0, color: 'amber' },
    { key: 'members', value: stats.value.members ?? 0, color: 'teal' },
    { key: 'byHand', value: stats.value.awarded_by_hand ?? 0, color: 'purple' },
]);

function narrowedLabel(achievement) {
    const metric = metrics.value.find(m => m.key === achievement.metric);
    const values = achievement.filters?.values || [];

    return values
        .map(value => metric?.choices?.find(c => c.value === value)?.label || value)
        .join(', ');
}

async function load() {
    loading.value = true;
    try {
        const [list, statsResponse] = await Promise.all([
            axios.get('/api/admin/achievements'),
            axios.get('/api/admin/achievements/stats'),
        ]);

        achievements.value = list.data.data || [];
        metrics.value = list.data.metrics || [];
        categories.value = list.data.categories || [];
        stats.value = statsResponse.data.data || {};
    } catch (error) {
        await dialog.requestError(error, t('achievements.admin.loadFailed'));
    } finally {
        loading.value = false;
    }
}

function openEditor(achievement = null) {
    clearPendingBadge();
    editing.value = achievement;
    form.value = achievement
        ? {
            ...achievement,
            filters: { values: achievement.filters?.values || [] },
        }
        : blankForm();
    showEditor.value = true;
}

async function save() {
    if (saving.value) return;

    const { valid } = await formRef.value.validate();
    if (!valid) return;

    saving.value = true;
    try {
        const payload = { ...form.value };

        if (payload.trigger !== 'metric' || !narrowChoices.value.length) {
            payload.filters = null;
        }

        if (editing.value) {
            await axios.patch(`/api/admin/achievements/${editing.value.id}`, payload);
        } else {
            const { data } = await axios.post('/api/admin/achievements', payload);

            // The picture chosen before the record existed goes up now
            if (pendingBadge.value) {
                await uploadBadge(data.data.id, pendingBadge.value);
            }
        }

        clearPendingBadge();
        showEditor.value = false;
        await load();
        await dialog.success(t('achievements.admin.saved'));
    } catch (error) {
        await dialog.requestError(error, t('achievements.admin.saveFailed'));
    } finally {
        saving.value = false;
    }
}

async function toggle(achievement, enabled) {
    busyId.value = achievement.id;
    try {
        await axios.patch(`/api/admin/achievements/${achievement.id}`, {
            ...achievement,
            is_enabled: enabled,
        });
        achievement.is_enabled = enabled;
    } catch (error) {
        await dialog.requestError(error, t('achievements.admin.saveFailed'));
    } finally {
        busyId.value = null;
    }
}

async function remove(achievement) {
    const confirmed = await dialog.confirm({
        title: t('achievements.admin.deleteTitle'),
        // Deleting takes it off everyone who holds it, which is worth saying
        message: t('achievements.admin.deleteConfirm', {
            name: achievement.name,
            count: achievement.holders_count,
        }),
        confirmText: t('common.delete'),
        confirmColor: 'error',
    });

    if (!confirmed) return;

    deletingId.value = achievement.id;
    try {
        await axios.delete(`/api/admin/achievements/${achievement.id}`);
        await load();
    } catch (error) {
        await dialog.requestError(error, t('achievements.admin.deleteFailed'));
    } finally {
        deletingId.value = null;
    }
}

async function openAward(achievement) {
    awarding.value = achievement;
    awardForm.value = { user_id: null, note: '' };
    userResults.value = [];
    showAward.value = true;

    try {
        const { data } = await axios.get(`/api/admin/achievements/${achievement.id}/holders`);
        holders.value = data.data || [];
    } catch {
        holders.value = [];
    }
}

let searchTimer = null;

function searchUsers(term) {
    if (!term || term.length < 2) return;

    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        searchingUsers.value = true;
        try {
            const { data } = await axios.post('/api/users/search', { search: term });
            userResults.value = data.data || data || [];
        } catch {
            userResults.value = [];
        } finally {
            searchingUsers.value = false;
        }
    }, 300);
}

async function award() {
    awardingBusy.value = true;
    try {
        const { data } = await axios.post(`/api/achievements/${awarding.value.id}/award`, awardForm.value);
        showAward.value = false;
        await load();
        await dialog.success(data.message);
    } catch (error) {
        await dialog.requestError(error, t('achievements.admin.awardFailed'));
    } finally {
        awardingBusy.value = false;
    }
}

async function revoke(holder) {
    try {
        await axios.post(`/api/achievements/${awarding.value.id}/revoke`, { user_id: holder.id });
        holders.value = holders.value.filter(h => h.id !== holder.id);
        await load();
    } catch (error) {
        await dialog.requestError(error, t('achievements.admin.revokeFailed'));
    }
}

onMounted(load);
</script>
