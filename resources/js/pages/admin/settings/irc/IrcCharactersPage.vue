<template>
    <settings-page-layout
        :title="$t('irc.admin.charactersTitle')"
        :description="$t('irc.admin.charactersDescription')"
        icon="mdi-account-edit-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'irc' } }"
        :show-save-button="false"
    >
        <settings-card icon="mdi-drama-masks" :title="$t('irc.admin.characters')">
            <div class="d-flex justify-space-between align-center mb-4">
                <span class="text-caption text-medium-emphasis">
                    {{ $t('irc.admin.charactersHint') }}
                </span>
                <v-btn color="primary" variant="flat" size="small" prepend-icon="mdi-plus" @click="openCreator()">
                    {{ $t('irc.admin.addCharacter') }}
                </v-btn>
            </div>

            <loading-state v-if="loading" compact />

            <empty-state
                v-else-if="!characters.length"
                compact
                icon="mdi-drama-masks"
                :title="$t('irc.admin.noCharacters')"
            />

            <v-row v-else>
                <v-col
                    v-for="character in characters"
                    :key="character.id"
                    cols="6"
                    sm="4"
                    md="3"
                    lg="2"
                >
                    <v-card variant="outlined" class="character-tile pa-2" :class="{ 'is-off': !character.is_enabled }">
                        <div class="d-flex justify-center">
                            <svg viewBox="0 0 100 140" class="character-tile-figure">
                                <comic-character :spec="character.spec" emotion="happy" />
                            </svg>
                        </div>

                        <div class="text-center text-body-2 font-weight-medium text-truncate" :title="character.name">
                            {{ character.name }}
                        </div>
                        <div class="text-center text-caption text-medium-emphasis">{{ character.key }}</div>

                        <div class="d-flex align-center justify-space-between mt-1">
                            <v-switch
                                :model-value="character.is_enabled"
                                density="compact"
                                hide-details
                                color="success"
                                :disabled="togglingId !== null"
                                :loading="togglingId === character.id"
                                @update:model-value="toggleEnabled(character, $event)"
                            />
                            <div>
                                <v-btn icon variant="text" size="small" @click="openCreator(character)">
                                    <v-icon size="small">mdi-pencil</v-icon>
                                </v-btn>
                                <v-btn
                                    v-if="!character.is_builtin"
                                    icon
                                    variant="text"
                                    size="small"
                                    color="error"
                                    :loading="deletingId === character.id"
                                    @click="remove(character)"
                                >
                                    <v-icon size="small">mdi-delete</v-icon>
                                </v-btn>
                                <v-tooltip v-else location="top">
                                    <template #activator="{ props }">
                                        <v-icon v-bind="props" size="small" class="ml-2 text-medium-emphasis">mdi-lock-outline</v-icon>
                                    </template>
                                    {{ $t('irc.admin.builtinCharacter') }}
                                </v-tooltip>
                            </div>
                        </div>
                    </v-card>
                </v-col>
            </v-row>
        </settings-card>

        <!-- The creator: pick the parts on the right, watch the character
             on the left change as you go. -->
        <v-dialog v-model="showCreator" max-width="960" persistent scrollable>
            <v-card>
                <v-card-title class="text-h6">
                    {{ editing ? $t('irc.admin.editCharacter') : $t('irc.admin.addCharacter') }}
                </v-card-title>
                <v-divider />

                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="4">
                            <div class="creator-stage">
                                <svg viewBox="0 0 100 140" class="creator-figure">
                                    <comic-character
                                        :spec="form.spec"
                                        :emotion="previewEmotion"
                                        :gesture="previewGesture"
                                    />
                                </svg>
                            </div>

                            <!-- The character has to hold up in every mood,
                                 so every mood is one click away -->
                            <div class="text-caption text-medium-emphasis mt-3 mb-1">
                                {{ $t('irc.admin.previewMood') }}
                            </div>
                            <v-chip-group v-model="previewEmotion" mandatory column>
                                <v-chip v-for="emotion in EMOTIONS" :key="emotion" size="x-small" :value="emotion">
                                    {{ $t(`irc.client.emotions.${emotion}`) }}
                                </v-chip>
                            </v-chip-group>

                            <v-chip-group v-model="previewGesture" column>
                                <v-chip v-for="gesture in GESTURES" :key="gesture" size="x-small" :value="gesture">
                                    {{ $t(`irc.client.gestures.${gesture}`) }}
                                </v-chip>
                            </v-chip-group>
                        </v-col>

                        <v-col cols="12" md="8">
                            <v-form ref="formRef" @submit.prevent="save">
                                <v-text-field
                                    v-model="form.name"
                                    :label="$t('irc.admin.form.characterName')"
                                    prepend-inner-icon="mdi-label"
                                    class="mb-3"
                                    :rules="[v => !!v || $t('irc.admin.form.required')]"
                                />

                                <v-row dense>
                                    <v-col v-for="part in PART_KEYS" :key="part" cols="12" sm="6">
                                        <v-select
                                            v-model="form.spec[part]"
                                            :items="optionsFor(part)"
                                            :label="$t(`irc.admin.parts.${part}`)"
                                            density="compact"
                                            variant="outlined"
                                            hide-details
                                            class="mb-2"
                                        />
                                    </v-col>

                                    <v-col cols="12" sm="6">
                                        <v-select
                                            v-model="form.spec.tone"
                                            :items="toneItems"
                                            :label="$t('irc.admin.parts.tone')"
                                            density="compact"
                                            variant="outlined"
                                            hide-details
                                            class="mb-2"
                                        />
                                    </v-col>
                                </v-row>

                                <div class="mt-3">
                                    <div class="text-caption text-medium-emphasis d-flex align-center ga-2">
                                        {{ $t('irc.admin.parts.hue') }}
                                        <span class="hue-dot" :style="{ background: hueSwatch }" />
                                    </div>
                                    <v-slider
                                        v-model="form.spec.hue"
                                        :min="0"
                                        :max="359"
                                        :step="1"
                                        hide-details
                                        class="hue-slider"
                                    />
                                    <p class="text-caption text-medium-emphasis mt-1">
                                        {{ $t('irc.admin.hueHint') }}
                                    </p>
                                </div>

                                <v-switch
                                    v-model="form.is_enabled"
                                    :label="$t('irc.admin.form.characterEnabled')"
                                    color="success"
                                    hide-details
                                    class="mt-2"
                                />
                            </v-form>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-divider />
                <v-card-actions>
                    <v-btn variant="text" @click="showCreator = false">{{ $t('common.cancel') }}</v-btn>
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
import ComicCharacter from '@/components/irc/ComicCharacterParts.vue';
import { useDialog } from '@/composables/useDialog.js';
import { DEFAULT_SPEC, PART_KEYS, PARTS, TONES, normaliseSpec } from '@/utils/comicCharacter.js';

defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

const EMOTIONS = ['normal', 'happy', 'sad', 'angry', 'surprised', 'confused', 'excited'];
const GESTURES = ['wave', 'laugh', 'think', 'shout', 'whisper'];

const { t } = useI18n();
const dialog = useDialog();

const loading = ref(true);
const saving = ref(false);
const characters = ref([]);
const showCreator = ref(false);
const editing = ref(null);
const deletingId = ref(null);
const togglingId = ref(null);
const formRef = ref(null);
const previewEmotion = ref('happy');
const previewGesture = ref(null);

const form = ref(blankForm());

function blankForm() {
    return { name: '', is_enabled: true, spec: { ...DEFAULT_SPEC } };
}

/**
 * The options for one part, named in the admin's language where a name
 * exists and falling back to the catalogue's own label.
 */
function optionsFor(part) {
    return Object.entries(PARTS[part].options).map(([value, option]) => ({
        value,
        title: t(`irc.admin.partOptions.${part}.${value}`, option.label),
    }));
}

const toneItems = computed(() => Object.keys(TONES).map(tone => ({
    value: tone,
    title: t(`irc.admin.tones.${tone}`, tone),
})));

const hueSwatch = computed(() => `hsl(${form.value.spec.hue}, 60%, 60%)`);

async function fetchCharacters() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/irc/comic-characters');
        characters.value = (data.data || []).map(character => ({
            ...character,
            spec: normaliseSpec(character.spec),
        }));
    } catch (error) {
        await dialog.requestError(error, t('irc.admin.charactersLoadFailed'));
    } finally {
        loading.value = false;
    }
}

function openCreator(character = null) {
    editing.value = character;
    form.value = character
        ? { name: character.name, is_enabled: character.is_enabled, spec: { ...normaliseSpec(character.spec) } }
        : blankForm();
    previewEmotion.value = 'happy';
    previewGesture.value = null;
    showCreator.value = true;
}

async function save() {
    if (saving.value) return;

    const { valid } = await formRef.value.validate();
    if (!valid) return;

    saving.value = true;
    try {
        if (editing.value) {
            await axios.patch(`/api/admin/irc/comic-characters/${editing.value.id}`, form.value);
        } else {
            await axios.post('/api/admin/irc/comic-characters', form.value);
        }
        showCreator.value = false;
        await fetchCharacters();
        await dialog.success(t('irc.admin.characterSaved'));
    } catch (error) {
        await dialog.requestError(error, t('irc.admin.characterSaveFailed'));
    } finally {
        saving.value = false;
    }
}

async function toggleEnabled(character, enabled) {
    togglingId.value = character.id;
    try {
        await axios.patch(`/api/admin/irc/comic-characters/${character.id}`, {
            name: character.name,
            spec: character.spec,
            is_enabled: enabled,
        });
        character.is_enabled = enabled;
    } catch (error) {
        await dialog.requestError(error, t('irc.admin.characterSaveFailed'));
    } finally {
        togglingId.value = null;
    }
}

async function remove(character) {
    const confirmed = await dialog.confirm({
        title: t('irc.admin.deleteCharacterTitle'),
        message: t('irc.admin.deleteCharacterConfirm', { name: character.name }),
        confirmText: t('common.delete'),
        confirmColor: 'error',
    });

    if (!confirmed) return;

    deletingId.value = character.id;
    try {
        await axios.delete(`/api/admin/irc/comic-characters/${character.id}`);
        await fetchCharacters();
    } catch (error) {
        await dialog.requestError(error, t('irc.admin.characterDeleteFailed'));
    } finally {
        deletingId.value = null;
    }
}

onMounted(fetchCharacters);
</script>

<style scoped>
.character-tile {
    transition: border-color 0.2s;
}

.character-tile.is-off {
    opacity: 0.55;
}

.character-tile-figure {
    width: 68px;
    height: 95px;
}

.creator-stage {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 8px;
    background: rgba(var(--v-theme-surface-variant), 0.3);
}

.creator-figure {
    width: 150px;
    height: 210px;
}

.hue-dot {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.hue-slider {
    margin-top: -4px;
}
</style>
