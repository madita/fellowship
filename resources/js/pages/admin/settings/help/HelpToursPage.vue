<template>
    <settings-page-layout
        :title="$t('help.admin.title')"
        :description="$t('help.admin.description')"
        icon="mdi-help-circle-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'help' } }"
        :show-save-button="false"
    >
        <!-- The master switch. The same setting the Features screen
             carries, offered here too because this is where somebody
             managing help looks for it. -->
        <settings-card icon="mdi-toggle-switch-outline" :title="$t('help.admin.feature')">
            <v-switch
                :model-value="featureEnabled"
                :label="$t('help.admin.featureLabel')"
                color="primary"
                density="compact"
                hide-details
                :loading="togglingFeature"
                :disabled="togglingFeature"
                @update:model-value="setFeatureEnabled"
            />
            <div class="text-caption text-medium-emphasis mt-1">
                {{ featureEnabled ? $t('help.admin.featureOnHint') : $t('help.admin.featureOffHint') }}
            </div>
        </settings-card>

        <settings-card icon="mdi-map-marker-path" :title="$t('help.admin.tours')">
            <div class="d-flex justify-space-between align-center mb-4 ga-2 flex-wrap">
                <span class="text-caption text-medium-emphasis">{{ $t('help.admin.hint') }}</span>
                <v-btn color="primary" variant="flat" size="small" prepend-icon="mdi-plus" @click="openEditor()">
                    {{ $t('help.admin.add') }}
                </v-btn>
            </div>

            <loading-state v-if="loading" compact />

            <empty-state
                v-else-if="!tours.length"
                compact
                icon="mdi-help-circle-outline"
                :title="$t('help.admin.empty')"
                :text="$t('help.admin.emptyHint')"
            />

            <v-table v-else density="comfortable">
                <thead>
                    <tr>
                        <th>{{ $t('help.admin.table.tour') }}</th>
                        <th>{{ $t('help.admin.table.page') }}</th>
                        <th>{{ $t('help.admin.table.mode') }}</th>
                        <th>{{ $t('help.admin.table.steps') }}</th>
                        <th>{{ $t('help.admin.table.enabled') }}</th>
                        <th class="text-right">{{ $t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tour in tours" :key="tour.id">
                        <td>
                            <div class="d-flex align-center ga-2">
                                <v-avatar :color="tour.color || 'primary'" size="30">
                                    <v-icon size="16" color="white">{{ tour.icon }}</v-icon>
                                </v-avatar>
                                <div>
                                    <div class="font-weight-medium">{{ titleOf(tour) }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t(`help.admin.audience.${tour.audience}`) }}
                                        <template v-if="tour.roles.length"> · {{ tour.roles.join(', ') }}</template>
                                        <template v-if="tour.auto_start"> · {{ $t('help.admin.startsItself') }}</template>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><code class="text-caption">{{ tour.route_pattern }}</code></td>
                        <td>
                            <v-chip size="x-small" variant="tonal" :color="tour.mode === 'hints' ? 'teal' : 'primary'">
                                {{ $t(`help.admin.mode.${tour.mode}`) }}
                            </v-chip>
                        </td>
                        <td>{{ tour.steps.length }}</td>
                        <td>
                            <v-switch
                                :model-value="tour.is_enabled"
                                density="compact"
                                hide-details
                                color="success"
                                :disabled="busyId !== null"
                                :loading="busyId === tour.id"
                                @update:model-value="toggle(tour, $event)"
                            />
                        </td>
                        <td class="text-right text-no-wrap">
                            <!-- A tour that is not live is not sent to
                                 anybody, so there would be nothing to see -->
                            <v-btn
                                icon
                                variant="text"
                                size="small"
                                :disabled="!tour.is_enabled"
                                :title="tour.is_enabled ? $t('help.admin.preview') : $t('help.admin.previewNeedsLive')"
                                @click="preview(tour)"
                            >
                                <v-icon size="small">mdi-play-circle-outline</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" size="small" :title="$t('common.edit')" @click="openEditor(tour)">
                                <v-icon size="small">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn
                                icon
                                variant="text"
                                size="small"
                                :title="$t('help.admin.duplicate')"
                                :loading="duplicatingId === tour.id"
                                @click="duplicate(tour)"
                            >
                                <v-icon size="small">mdi-content-copy</v-icon>
                            </v-btn>
                            <v-btn
                                icon
                                variant="text"
                                size="small"
                                color="error"
                                :title="$t('common.delete')"
                                :loading="deletingId === tour.id"
                                @click="remove(tour)"
                            >
                                <v-icon size="small">mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </settings-card>

        <v-dialog v-model="showEditor" max-width="860" persistent scrollable>
            <v-card>
                <v-card-title class="text-h6">
                    {{ editing ? $t('help.admin.edit') : $t('help.admin.add') }}
                </v-card-title>
                <v-divider />

                <v-card-text>
                    <v-form ref="formRef" @submit.prevent="save">
                        <!-- Where and for whom -->
                        <div class="text-caption text-medium-emphasis mb-2">{{ $t('help.admin.form.where') }}</div>

                        <v-row dense>
                            <v-col cols="12" md="7">
                                <v-combobox
                                    v-model="form.route_pattern"
                                    :items="routeOptions"
                                    item-title="pattern"
                                    item-value="pattern"
                                    :return-object="false"
                                    :label="$t('help.admin.form.page')"
                                    :hint="$t('help.admin.form.pageHint')"
                                    persistent-hint
                                    density="compact"
                                    variant="outlined"
                                    :rules="[v => !!v || $t('common.required')]"
                                >
                                    <template #item="{ props: itemProps, item }">
                                        <v-list-item v-bind="itemProps" :title="item.raw.label">
                                            <v-list-item-subtitle>
                                                <code class="text-caption">{{ item.raw.pattern }}</code>
                                            </v-list-item-subtitle>
                                        </v-list-item>
                                    </template>
                                </v-combobox>
                            </v-col>
                            <v-col cols="12" md="5">
                                <v-select
                                    v-model="form.mode"
                                    :items="modeOptions"
                                    :label="$t('help.admin.form.mode')"
                                    :hint="$t(`help.admin.modeHint.${form.mode}`)"
                                    persistent-hint
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>

                        <v-row dense class="mt-1">
                            <v-col cols="12" sm="4">
                                <v-select
                                    v-model="form.audience"
                                    :items="audienceOptions"
                                    :label="$t('help.admin.form.audience')"
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="8">
                                <v-select
                                    v-model="form.roles"
                                    :items="roles"
                                    :label="$t('help.admin.form.roles')"
                                    :hint="$t('help.admin.form.rolesHint')"
                                    persistent-hint
                                    multiple
                                    chips
                                    closable-chips
                                    clearable
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>

                        <v-row dense class="mt-1">
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="form.icon"
                                    :label="$t('help.admin.form.icon')"
                                    density="compact"
                                    variant="outlined"
                                    :prepend-inner-icon="form.icon || 'mdi-help-circle-outline'"
                                />
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-select
                                    v-model="form.color"
                                    :items="colors"
                                    :label="$t('help.admin.form.color')"
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model.number="form.sort_order"
                                    :label="$t('help.admin.form.order')"
                                    type="number"
                                    min="0"
                                    density="compact"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>

                        <div class="d-flex flex-wrap ga-4">
                            <v-switch
                                v-model="form.auto_start"
                                :label="$t('help.admin.form.autoStart')"
                                :disabled="form.mode === 'hints'"
                                :messages="form.mode === 'hints' ? $t('help.admin.form.autoStartHints') : ''"
                                color="primary"
                                density="compact"
                                hide-details="auto"
                            />
                            <v-switch
                                v-model="form.show_launcher"
                                :label="$t('help.admin.form.showLauncher')"
                                color="primary"
                                density="compact"
                                hide-details
                            />
                            <v-switch
                                v-model="form.is_enabled"
                                :label="$t('help.admin.form.enabled')"
                                color="success"
                                density="compact"
                                hide-details
                            />
                        </div>

                        <v-divider class="my-4" />

                        <!-- What it says, in every language -->
                        <div class="text-caption text-medium-emphasis mb-2">{{ $t('help.admin.form.wording') }}</div>

                        <v-tabs v-model="activeLocale" density="compact" class="mb-3">
                            <v-tab v-for="locale in locales" :key="locale" :value="locale">
                                {{ locale.toUpperCase() }}
                            </v-tab>
                        </v-tabs>

                        <v-window v-model="activeLocale">
                            <v-window-item v-for="locale in locales" :key="locale" :value="locale">
                                <v-text-field
                                    v-model="form.translations[locale].title"
                                    :label="$t('help.admin.form.tourTitle')"
                                    :rules="locale === defaultLocale ? [v => !!v || $t('common.required')] : []"
                                    :hint="locale === defaultLocale ? '' : $t('help.admin.form.fallsBack', { locale: defaultLocale.toUpperCase() })"
                                    persistent-hint
                                    density="compact"
                                    variant="outlined"
                                    class="mb-2"
                                />
                                <v-textarea
                                    v-model="form.translations[locale].description"
                                    :label="$t('help.admin.form.tourDescription')"
                                    rows="2"
                                    density="compact"
                                    variant="outlined"
                                />

                                <v-divider class="my-3" />

                                <div class="d-flex justify-space-between align-center mb-2 ga-2 flex-wrap">
                                    <span class="text-caption text-medium-emphasis">
                                        {{ $t('help.admin.form.steps', { count: form.steps.length }, form.steps.length) }}
                                    </span>
                                    <v-btn size="x-small" variant="tonal" prepend-icon="mdi-plus" @click="addStep">
                                        {{ $t('help.admin.form.addStep') }}
                                    </v-btn>
                                </div>

                                <empty-state
                                    v-if="!form.steps.length"
                                    compact
                                    icon="mdi-format-list-numbered"
                                    :title="$t('help.admin.form.noSteps')"
                                />

                                <v-expansion-panels v-else variant="accordion" class="help-steps">
                                    <v-expansion-panel v-for="(step, index) in form.steps" :key="step.uid">
                                        <v-expansion-panel-title>
                                            <div class="d-flex align-center ga-2 flex-grow-1 overflow-hidden">
                                                <v-chip size="x-small" variant="tonal">{{ index + 1 }}</v-chip>
                                                <span class="text-truncate">
                                                    {{ step.translations[locale].title || $t('help.admin.form.untitledStep') }}
                                                </span>
                                            </div>
                                        </v-expansion-panel-title>

                                        <v-expansion-panel-text>
                                            <v-text-field
                                                v-model="step.translations[locale].title"
                                                :label="$t('help.admin.form.stepTitle')"
                                                :rules="locale === defaultLocale ? [v => !!v || $t('common.required')] : []"
                                                density="compact"
                                                variant="outlined"
                                                class="mb-2"
                                            />
                                            <v-textarea
                                                v-model="step.translations[locale].content"
                                                :label="$t('help.admin.form.stepContent')"
                                                rows="3"
                                                density="compact"
                                                variant="outlined"
                                                class="mb-2"
                                            />

                                            <!-- The target is the same in every language, so it
                                                 is only offered on the fallback tab -->
                                            <template v-if="locale === defaultLocale">
                                                <v-text-field
                                                    v-model="step.target"
                                                    :label="$t('help.admin.form.target')"
                                                    :hint="$t('help.admin.form.targetHint')"
                                                    persistent-hint
                                                    density="compact"
                                                    variant="outlined"
                                                    clearable
                                                    class="mb-2"
                                                >
                                                    <template #append>
                                                        <v-btn
                                                            size="small"
                                                            variant="tonal"
                                                            prepend-icon="mdi-target"
                                                            @click="pickOnPage(index)"
                                                        >
                                                            {{ $t('help.admin.form.pick') }}
                                                        </v-btn>
                                                    </template>
                                                </v-text-field>

                                                <v-select
                                                    v-model="step.placement"
                                                    :items="placementOptions"
                                                    :label="$t('help.admin.form.placement')"
                                                    density="compact"
                                                    variant="outlined"
                                                    class="mb-2"
                                                />
                                            </template>
                                            <div v-else class="text-caption text-medium-emphasis mb-2">
                                                {{ $t('help.admin.form.targetElsewhere', { locale: defaultLocale.toUpperCase() }) }}
                                                <code v-if="step.target" class="ml-1">{{ step.target }}</code>
                                            </div>

                                            <div class="d-flex ga-1">
                                                <v-btn
                                                    icon
                                                    variant="text"
                                                    size="x-small"
                                                    :disabled="index === 0"
                                                    :title="$t('help.admin.form.moveUp')"
                                                    @click="moveStep(index, -1)"
                                                >
                                                    <v-icon size="18">mdi-arrow-up</v-icon>
                                                </v-btn>
                                                <v-btn
                                                    icon
                                                    variant="text"
                                                    size="x-small"
                                                    :disabled="index === form.steps.length - 1"
                                                    :title="$t('help.admin.form.moveDown')"
                                                    @click="moveStep(index, 1)"
                                                >
                                                    <v-icon size="18">mdi-arrow-down</v-icon>
                                                </v-btn>
                                                <v-spacer />
                                                <v-btn
                                                    variant="text"
                                                    size="x-small"
                                                    color="error"
                                                    prepend-icon="mdi-delete"
                                                    @click="removeStep(index)"
                                                >
                                                    {{ $t('help.admin.form.removeStep') }}
                                                </v-btn>
                                            </div>
                                        </v-expansion-panel-text>
                                    </v-expansion-panel>
                                </v-expansion-panels>
                            </v-window-item>
                        </v-window>
                    </v-form>
                </v-card-text>

                <v-divider />
                <v-card-actions>
                    <v-btn variant="text" @click="closeEditor">{{ $t('common.cancel') }}</v-btn>
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
import { computed, nextTick, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import axios from 'axios';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import { useDialog } from '@/composables/useDialog.js';
import { useHelpStore, PICK_FLAG } from '@/store/helpStore.js';
import { useSettingsStore } from '@/store/settingStore.js';
import { helpRouteOptions } from '@/utils/helpRoutes.js';
import { normalisePath } from '@/utils/helpAnchor.js';

/**
 * Writing the help: which page it belongs to, who it is for, and what it
 * says about what.
 *
 * A tour and its steps are saved together in one request, because the step
 * list is dragged about and half rewritten before anybody presses save.
 *
 * Pointing a step at something is done on the real page: "Pick on page"
 * parks the draft, sends the admin to the page with picking mode on, and
 * the selector they clicked is waiting here when they come back.
 */

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const COLORS = ['primary', 'teal', 'blue', 'indigo', 'deep-purple', 'amber', 'orange', 'green', 'red'];

const { t } = useI18n();
const dialog = useDialog();
const router = useRouter();
const helpStore = useHelpStore();
const settingsStore = useSettingsStore();

const loading = ref(true);
const saving = ref(false);
const tours = ref([]);
const locales = ref(['en']);
const defaultLocale = ref('en');
const activeLocale = ref('en');
const roles = ref([]);

const busyId = ref(null);
const deletingId = ref(null);
const duplicatingId = ref(null);

// Whether the whole feature is on. Takes effect the moment it is
// switched — nobody expects to have to find a Save button for a master
// switch — so it is written on its own rather than with the page.
const featureEnabled = ref(true);
const togglingFeature = ref(false);

// Steps are reordered and deleted before they have ids, so the list needs
// a key of its own that survives the shuffling. Declared above the form,
// which is built by calling blankStep() as this runs: a `let` below it
// would still be in its dead zone and setup would throw.
let uid = 0;

const showEditor = ref(false);
const editing = ref(null);
const formRef = ref(null);
const form = ref(blankForm());

const colors = COLORS;

// Every page the router knows about, so the pattern does not have to be
// remembered — the field stays free text for the ones it cannot express
const routeOptions = computed(() => helpRouteOptions(router.getRoutes()));

const modeOptions = computed(() => [
    { value: 'tour', title: t('help.admin.mode.tour') },
    { value: 'hints', title: t('help.admin.mode.hints') },
]);

const audienceOptions = computed(() => ['everyone', 'members', 'guests'].map(value => ({
    value,
    title: t(`help.admin.audience.${value}`),
})));

const placementOptions = computed(() => ['auto', 'top', 'bottom', 'left', 'right'].map(value => ({
    value,
    title: t(`help.admin.placement.${value}`),
})));

function blankWording(fields) {
    return Object.fromEntries(
        locales.value.map(locale => [locale, Object.fromEntries(fields.map(field => [field, '']))])
    );
}

function blankStep() {
    return {
        uid: ++uid,
        id: null,
        target: '',
        placement: 'auto',
        translations: blankWording(['title', 'content']),
    };
}

function blankForm() {
    return {
        route_pattern: '',
        mode: 'tour',
        icon: 'mdi-help-circle-outline',
        color: 'primary',
        audience: 'everyone',
        roles: [],
        auto_start: false,
        show_launcher: true,
        is_enabled: true,
        sort_order: 0,
        translations: blankWording(['title', 'description']),
        steps: [blankStep()],
    };
}

/** What a tour is called, in whichever language it has a name. */
function titleOf(tour) {
    const wording = tour.translations || {};

    return wording[defaultLocale.value]?.title
        || Object.values(wording).find(entry => entry.title)?.title
        || tour.key;
}

/**
 * Turn the feature on or off site-wide: the help button in the toolbar,
 * the tours that start by themselves, and the hints on every page.
 *
 * The tours themselves are left alone and stay editable here, so an admin
 * can write them with the feature off and switch it on when they are
 * ready — the same way the other feature toggles behave.
 */
async function setFeatureEnabled(value) {
    const wanted = value !== false;

    togglingFeature.value = true;

    try {
        await axios.post('/api/admin/settings', {
            settings: [{ key: 'feature_help_enabled', value: wanted, type: 'boolean' }],
        });

        // Re-read rather than assume: the store is what the toolbar and
        // every page actually consult
        await settingsStore.fetchAppSettings();
        featureEnabled.value = settingsStore.isFeatureEnabled('help');
    } catch (error) {
        // Put the switch back where it was, so it never shows an answer
        // that was not saved
        featureEnabled.value = settingsStore.isFeatureEnabled('help');
        await dialog.requestError(error, t('help.admin.featureSaveFailed'));
    } finally {
        togglingFeature.value = false;
    }
}

async function load() {
    featureEnabled.value = settingsStore.isFeatureEnabled('help');
    loading.value = true;

    try {
        const { data } = await axios.get('/api/admin/help-tours');

        locales.value = data.locales?.length ? data.locales : ['en'];
        defaultLocale.value = data.default && locales.value.includes(data.default)
            ? data.default
            : locales.value[0];
        activeLocale.value = defaultLocale.value;
        roles.value = data.roles || [];
        tours.value = data.data || [];
    } catch (error) {
        await dialog.requestError(error, t('help.admin.loadFailed'));
    } finally {
        loading.value = false;
    }
}

/** Fill in the locales a record says nothing in, so every tab has a field. */
function wordingFrom(stored, fields) {
    const wording = blankWording(fields);

    for (const [locale, entry] of Object.entries(stored || {})) {
        if (!wording[locale]) continue;

        for (const field of fields) {
            wording[locale][field] = entry[field] || '';
        }
    }

    return wording;
}

function formFrom(tour) {
    return {
        route_pattern: tour.route_pattern,
        mode: tour.mode,
        icon: tour.icon,
        color: tour.color,
        audience: tour.audience,
        roles: [...(tour.roles || [])],
        auto_start: tour.auto_start,
        show_launcher: tour.show_launcher,
        is_enabled: tour.is_enabled,
        sort_order: tour.sort_order,
        translations: wordingFrom(tour.translations, ['title', 'description']),
        steps: (tour.steps || []).map(step => ({
            uid: ++uid,
            id: step.id,
            target: step.target || '',
            placement: step.placement || 'auto',
            translations: wordingFrom(step.translations, ['title', 'content']),
        })),
    };
}

function openEditor(tour = null) {
    editing.value = tour;
    activeLocale.value = defaultLocale.value;
    form.value = tour ? formFrom(tour) : blankForm();
    showEditor.value = true;
}

function closeEditor() {
    showEditor.value = false;
    editing.value = null;
    helpStore.cancelPicking();
}

function addStep() {
    form.value.steps.push(blankStep());
}

function removeStep(index) {
    form.value.steps.splice(index, 1);
}

function moveStep(index, by) {
    const to = index + by;
    if (to < 0 || to >= form.value.steps.length) return;

    const [step] = form.value.steps.splice(index, 1);
    form.value.steps.splice(to, 0, step);
}

/**
 * Go and click the element this step is about.
 *
 * The draft is parked first, so nothing written so far is lost; the page
 * it opens is the one the tour is for, with a wildcard pattern reduced to
 * something that can actually be navigated to.
 */
async function pickOnPage(index) {
    const target = navigablePath(form.value.route_pattern);

    if (!target) {
        await dialog.warning(t('help.admin.form.pickNeedsPage'));

        return;
    }

    const parked = helpStore.beginPicking({
        draft: { editingId: editing.value?.id ?? null, form: form.value },
        stepIndex: index,
        returnTo: { name: 'admin-settings-page', params: { category: 'help', setting: 'tours' } },
    });

    if (!parked) {
        await dialog.error(t('help.admin.form.pickUnavailable'));

        return;
    }

    router.push({ path: target, query: { [PICK_FLAG]: 1 } });
}

/**
 * A pattern turned into a path a browser can open. `/wiki/*` has no one
 * page, so the wildcard is dropped and the section's own page is used —
 * close enough to pick a toolbar or a sidebar from, and the admin can
 * always navigate further once picking mode is on.
 */
function navigablePath(pattern) {
    const raw = String(pattern || '').trim();

    // Checked before tidying, because tidying turns an empty pattern into
    // the root — and "no page chosen yet" must not read as "the landing
    // page", which is where picking used to send the admin
    if (!raw.startsWith('/')) return null;

    return normalisePath(raw.split('*')[0]);
}

/** Picking is done: put the draft back and drop the selector into its step. */
function restorePick() {
    const pending = helpStore.takePick();
    if (!pending?.draft?.form) return;

    const { draft, stepIndex, selector } = pending;

    editing.value = draft.editingId
        ? tours.value.find(tour => tour.id === draft.editingId) || null
        : null;

    form.value = draft.form;

    // The step list can only have shrunk if somebody edited it in another
    // tab, so this is a guard rather than an expectation
    if (form.value.steps[stepIndex]) {
        form.value.steps[stepIndex].target = selector;
    }

    activeLocale.value = defaultLocale.value;
    showEditor.value = true;
}

/**
 * Run the tour where it lives, which is the only place it can be checked.
 * Saving first, because a preview of an unsaved draft would be a lie.
 */
async function preview(tour) {
    const target = navigablePath(tour.route_pattern);

    if (!target) {
        await dialog.warning(t('help.admin.previewFailed'));

        return;
    }

    router.push({ path: target, query: { help_tour: tour.key } });
}

async function save() {
    if (saving.value) return;

    const { valid } = await formRef.value.validate();
    if (!valid) return;

    saving.value = true;

    try {
        const payload = {
            ...form.value,
            // The editor's own bookkeeping is no business of the server
            steps: form.value.steps.map(({ uid: _uid, ...step }) => step),
        };

        if (editing.value) {
            await axios.patch(`/api/admin/help-tours/${editing.value.id}`, payload);
        } else {
            await axios.post('/api/admin/help-tours', payload);
        }

        showEditor.value = false;
        editing.value = null;
        await load();
        await dialog.success(t('help.admin.saved'));
    } catch (error) {
        await dialog.requestError(error, t('help.admin.saveFailed'));
    } finally {
        saving.value = false;
    }
}

async function toggle(tour, enabled) {
    busyId.value = tour.id;

    try {
        // Saving is all-or-nothing, so the switch sends the tour as it
        // stands with only that one answer changed
        const current = formFrom(tour);

        await axios.patch(`/api/admin/help-tours/${tour.id}`, {
            ...current,
            steps: current.steps.map(({ uid: _uid, ...step }) => step),
            is_enabled: enabled,
        });

        tour.is_enabled = enabled;
    } catch (error) {
        await dialog.requestError(error, t('help.admin.saveFailed'));
    } finally {
        busyId.value = null;
    }
}

async function duplicate(tour) {
    duplicatingId.value = tour.id;

    try {
        await axios.post(`/api/admin/help-tours/${tour.id}/duplicate`);
        await load();
    } catch (error) {
        await dialog.requestError(error, t('help.admin.duplicateFailed'));
    } finally {
        duplicatingId.value = null;
    }
}

async function remove(tour) {
    const confirmed = await dialog.confirmDelete(
        t('help.admin.deleteConfirm', { name: titleOf(tour) })
    );

    if (!confirmed) return;

    deletingId.value = tour.id;

    try {
        await axios.delete(`/api/admin/help-tours/${tour.id}`);
        await load();
    } catch (error) {
        await dialog.requestError(error, t('help.admin.deleteFailed'));
    } finally {
        deletingId.value = null;
    }
}

onMounted(async () => {
    await load();

    // Coming back from picking an element: the editor reopens as it was
    await nextTick();
    restorePick();
});
</script>

<style lang="scss" scoped>
.help-steps {
    // The panels sit inside a dialog that scrolls; without this the
    // open one can push the actions off the bottom
    max-height: 420px;
    overflow-y: auto;
}
</style>
