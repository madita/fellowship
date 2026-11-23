<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog.js';

/**
 * Add an entry to a vocabulary without leaving the form that needs it.
 *
 * Four screens had their own half of this - the wiki editor, the page
 * form, the admin drawer and the category screens - and each of them
 * offered a parent as a flat list of names, or offered none at all, so a
 * new category was invented at the root and moved later, or by leaving
 * the half-written page and coming back.
 *
 * The parent is the field this dialog is for. Each candidate is offered
 * with the path above it and indented by its depth, so "Characters" under
 * Lore and "Characters" under Rules can be told apart before the choice
 * is made rather than after.
 *
 * It speaks one protocol, whatever the vocabulary: it reads
 * { data: [{ id, title, parent_id, depth, path }] } from the options
 * endpoint and posts { term, taxonomy, parent_id, content }. A screen
 * with an endpoint of its own - the wiki, which gates categories behind
 * its own permission - passes it in; everything else uses the generic
 * one.
 */

const { t } = useI18n();
const dialog = useDialog();

const emit = defineEmits(['update:modelValue', 'created']);

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** The vocabulary an entry is being added to: wiki, category, tags… */
    vocabulary: { type: String, required: true },
    /** What one entry is called here, capitalised: "Category", "Tag". */
    label: { type: String, default: '' },
    /** Prefilled name — whatever was typed into the field. */
    name: { type: String, default: '' },
    /** Preselected parent, as the id of an entry of this vocabulary. */
    parentId: { type: [Number, String], default: null },
    /** Where the tree comes from, when it is not the generic endpoint. */
    optionsEndpoint: { type: String, default: '' },
    /** Where the entry is posted, when it is not the generic endpoint. */
    createEndpoint: { type: String, default: '' },
    /** Flat vocabularies — tags — have nothing to sit under. */
    withParent: { type: Boolean, default: true },
    withDescription: { type: Boolean, default: true },
});

const form = ref({ title: '', parent_id: null, description: '' });
const errors = ref({});
const saving = ref(false);

const options = ref([]);
const loadingOptions = ref(false);
const failed = ref(false);

const show = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const label = computed(() => props.label || t('taxonomy.dialog.fallbackLabel'));

const optionsUrl = computed(() => props.optionsEndpoint || `/api/tag/options/${props.vocabulary}`);
const createUrl = computed(() => props.createEndpoint || '/api/tag/terms');

const parentItems = computed(() => options.value.map((option) => ({
    value: option.id,
    title: option.title,
    depth: option.depth || 0,
    path: option.path || [option.title],
})));

/**
 * The name already in use. Said before saving, because the server refuses
 * it and the answer is to pick the existing one, not to try again.
 */
const existing = computed(() => {
    const title = String(form.value.title ?? '').trim().toLowerCase();

    if (!title) return null;

    return options.value.find((option) => String(option.title).toLowerCase() === title) || null;
});

const fetchOptions = async () => {
    loadingOptions.value = true;
    failed.value = false;

    try {
        const { data } = await axios.get(optionsUrl.value);

        options.value = data?.data || [];
    } catch (error) {
        options.value = [];
        failed.value = true;
    } finally {
        loadingOptions.value = false;
    }
};

watch(() => [props.modelValue, props.name, props.parentId, props.vocabulary], () => {
    if (!props.modelValue) return;

    errors.value = {};
    form.value = {
        title: props.name || '',
        parent_id: props.parentId ?? null,
        description: '',
    };

    fetchOptions();
}, { immediate: true });

const firstError = (field) => errors.value?.[field]?.[0] || '';

const save = async () => {
    if (saving.value) return;

    errors.value = {};

    const title = String(form.value.title ?? '').trim();

    if (!title) {
        errors.value = { term: [t('taxonomy.dialog.nameRequired')] };

        return;
    }

    saving.value = true;

    try {
        const { data } = await axios.post(createUrl.value, {
            term: title,
            taxonomy: props.vocabulary,
            parent_id: props.withParent ? (form.value.parent_id ?? null) : null,
            content: props.withDescription ? (form.value.description || null) : null,
        });

        show.value = false;
        // The created entry travels back in the shape the pickers read, so
        // the form that asked for it can file itself under it straight away.
        emit('created', data?.category ?? { title });
        dialog.success(data?.message || t('taxonomy.dialog.created', { label: label.value }));
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data?.errors || {};
        } else {
            dialog.requestError(error, t('taxonomy.dialog.saveFailed'));
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <VDialog v-model="show" max-width="560" scrollable>
        <VCard>
            <VCardTitle class="d-flex align-center ga-2">
                <VIcon size="20" color="primary">mdi-folder-plus-outline</VIcon>
                {{ $t('taxonomy.dialog.title', { label }) }}
            </VCardTitle>

            <VDivider />

            <VCardText>
                <VTextField
                    v-model="form.title"
                    :label="$t('taxonomy.dialog.name')"
                    :hint="$t('taxonomy.dialog.nameHint')"
                    persistent-hint
                    :error-messages="firstError('term')"
                    variant="outlined"
                    density="comfortable"
                    autofocus
                    class="mb-2"
                />

                <VAlert
                    v-if="existing && !firstError('term')"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                    :text="$t('taxonomy.dialog.nameTaken', { path: existing.path.join(' › ') })"
                />

                <!-- The parent, with the branch it sits in spelled out -->
                <VAutocomplete
                    v-if="withParent"
                    v-model="form.parent_id"
                    :items="parentItems"
                    item-title="title"
                    item-value="value"
                    :label="$t('taxonomy.dialog.parent')"
                    :placeholder="$t('taxonomy.dialog.noParent')"
                    :hint="$t('taxonomy.dialog.parentHint')"
                    persistent-hint
                    :loading="loadingOptions"
                    :error-messages="firstError('parent_id')"
                    :no-data-text="failed ? $t('taxonomy.dialog.loadFailed') : $t('taxonomy.dialog.noOptions')"
                    clearable
                    variant="outlined"
                    density="comfortable"
                    class="mt-4 mb-2"
                >
                    <template #item="{ props: itemProps, item }">
                        <VListItem
                            v-bind="itemProps"
                            :title="undefined"
                            :style="{ paddingInlineStart: `${12 + item.raw.depth * 18}px` }"
                        >
                            <template #prepend>
                                <VIcon size="16" class="me-2">
                                    {{ item.raw.depth ? 'mdi-subdirectory-arrow-right' : 'mdi-folder-outline' }}
                                </VIcon>
                            </template>
                            <VListItemTitle>{{ item.raw.title }}</VListItemTitle>
                            <VListItemSubtitle v-if="item.raw.path.length > 1">
                                {{ item.raw.path.join(' › ') }}
                            </VListItemSubtitle>
                        </VListItem>
                    </template>

                    <template #selection="{ item }">
                        <span class="text-truncate">
                            <span v-if="item.raw.path?.length > 1" class="text-medium-emphasis">
                                {{ item.raw.path.slice(0, -1).join(' › ') }} ›
                            </span>
                            {{ item.raw.title }}
                        </span>
                    </template>
                </VAutocomplete>

                <VTextarea
                    v-if="withDescription"
                    v-model="form.description"
                    :label="$t('taxonomy.dialog.description')"
                    :error-messages="firstError('content')"
                    variant="outlined"
                    density="comfortable"
                    rows="2"
                    auto-grow
                    class="mt-4"
                    hide-details="auto"
                />
            </VCardText>

            <VDivider />

            <VCardActions>
                <VSpacer />
                <VBtn variant="text" :disabled="saving" @click="show = false">
                    {{ $t('common.cancel') }}
                </VBtn>
                <VBtn
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-folder-plus"
                    :loading="saving"
                    :disabled="saving"
                    @click="save"
                >
                    {{ $t('taxonomy.dialog.create', { label }) }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>
