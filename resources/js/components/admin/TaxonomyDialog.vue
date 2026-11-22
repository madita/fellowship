<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog.js';

/**
 * Create or edit one taxonomy entry.
 *
 * The two fields that used to be guesswork are the point of this dialog.
 * "Name" is the term the entry is named by — typed freely, because a
 * vocabulary entry is just a word put to use — and "Parent" offers the
 * full path of each candidate, scoped to the same vocabulary and with the
 * entry's own branch left out, so there is no way to pick something that
 * would make an entry its own ancestor.
 */

const { t } = useI18n();
const dialog = useDialog();

const emit = defineEmits(['update:modelValue', 'saved']);

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** The entry being edited, or null when creating. */
    node: { type: Object, default: null },
    /** Vocabulary the new entry lands in. */
    vocabulary: { type: String, default: '' },
    /** The vocabularies in use, as [{ taxonomy, label, total }]. */
    vocabularies: { type: Array, default: () => [] },
    /** Pre-selected parent, when created from a row's "add child" button. */
    parentId: { type: [Number, String], default: null },
});

const form = ref(emptyForm());
const errors = ref({});
const saving = ref(false);

const terms = ref([]);
const termSearch = ref('');
const loadingTerms = ref(false);

const parents = ref([]);
const loadingParents = ref(false);

const isEdit = computed(() => Boolean(props.node?.id));

function emptyForm() {
    return {
        title: '',
        taxonomy: '',
        parent_id: null,
        sort: null,
        visible: true,
        searchable: true,
        color: null,
        description: '',
        lead: '',
    };
}

const show = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

/** Terms offered as a name. Already-used ones stay visible but are flagged. */
const termItems = computed(() => terms.value.map((term) => ({
    value: term.title,
    title: term.title,
    slug: term.slug,
    taken: term.taken,
})));

const vocabularyItems = computed(() => props.vocabularies.map((vocabulary) => ({
    value: vocabulary.taxonomy,
    title: vocabulary.taxonomy,
    label: vocabulary.label || vocabulary.taxonomy,
})));

const parentItems = computed(() => parents.value.map((option) => ({
    value: option.id,
    title: option.title,
    depth: option.depth || 0,
    path: option.path || [option.title],
})));

const fetchTerms = async () => {
    loadingTerms.value = true;

    try {
        const { data } = await axios.get('/api/admin/taxonomy/terms', {
            params: {
                q: termSearch.value || undefined,
                taxonomy: form.value.taxonomy || undefined,
                exclude: props.node?.id || undefined,
            },
        });

        terms.value = data?.data || [];
    } catch (error) {
        terms.value = [];
    } finally {
        loadingTerms.value = false;
    }
};

const fetchParents = async () => {
    if (!form.value.taxonomy) {
        parents.value = [];

        return;
    }

    loadingParents.value = true;

    try {
        const { data } = await axios.get('/api/admin/taxonomy/parent-options', {
            params: {
                taxonomy: form.value.taxonomy,
                exclude: props.node?.id || undefined,
            },
        });

        parents.value = data?.data || [];
    } catch (error) {
        parents.value = [];
    } finally {
        loadingParents.value = false;
    }
};

/**
 * A parent that is no longer on offer (another vocabulary, or removed) is
 * dropped rather than submitted back, and the admin is told why.
 */
const parentDropped = ref(false);

watch(() => [props.modelValue, props.node, props.vocabulary, props.parentId], () => {
    if (!props.modelValue) return;

    errors.value = {};
    parentDropped.value = false;
    termSearch.value = '';

    form.value = props.node
        ? {
            title: props.node.title || '',
            taxonomy: props.node.taxonomy || props.vocabulary || '',
            parent_id: props.node.parent_id ?? null,
            sort: props.node.sort ?? null,
            visible: props.node.visible !== false,
            searchable: props.node.searchable !== false,
            color: props.node.color || null,
            description: props.node.description || '',
            lead: props.node.lead || '',
        }
        : { ...emptyForm(), taxonomy: props.vocabulary || '', parent_id: props.parentId ?? null };

    fetchTerms();
    fetchParents();
}, { immediate: true });

// Moving an entry to another vocabulary invalidates the parent it had.
watch(() => form.value.taxonomy, (next, previous) => {
    if (!props.modelValue || previous === undefined) return;

    if (next !== previous && form.value.parent_id !== null) {
        form.value.parent_id = null;
        parentDropped.value = true;
    }

    fetchParents();
    fetchTerms();
});

let termTimer = null;

watch(termSearch, () => {
    clearTimeout(termTimer);
    termTimer = setTimeout(fetchTerms, 250);
});

const save = async () => {
    if (saving.value) return;

    errors.value = {};

    const title = String(form.value.title ?? '').trim();

    if (!title) {
        errors.value = { title: [t('admin.taxonomies.dialog.nameRequired')] };

        return;
    }

    if (!form.value.taxonomy) {
        errors.value = { taxonomy: [t('admin.taxonomies.dialog.vocabularyRequired')] };

        return;
    }

    saving.value = true;

    const payload = {
        title,
        taxonomy: form.value.taxonomy,
        parent_id: form.value.parent_id ?? null,
        visible: form.value.visible,
        searchable: form.value.searchable,
        color: form.value.color || null,
        description: form.value.description || null,
        lead: form.value.lead || null,
    };

    // Left out when untouched, so the model's "put it last" default still
    // applies to a new entry.
    if (form.value.sort !== null && form.value.sort !== '') {
        payload.sort = Number(form.value.sort);
    }

    try {
        const { data } = isEdit.value
            ? await axios.patch(`/api/admin/taxonomy/${props.node.id}`, payload)
            : await axios.post('/api/admin/taxonomy', payload);

        show.value = false;
        emit('saved', data?.data ?? null);
        dialog.success(data?.message || t('admin.taxonomies.saved'));
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data?.errors || {};
        } else {
            dialog.requestError(error, t('errors.general'));
        }
    } finally {
        saving.value = false;
    }
};

const firstError = (field) => errors.value?.[field]?.[0] || '';
</script>

<template>
    <VDialog v-model="show" max-width="620" scrollable>
        <VCard>
            <VCardTitle class="d-flex align-center ga-2">
                <VIcon size="20">{{ isEdit ? 'mdi-pencil' : 'mdi-plus' }}</VIcon>
                {{ isEdit ? $t('admin.taxonomies.dialog.editTitle') : $t('admin.taxonomies.dialog.createTitle') }}
            </VCardTitle>

            <VDivider />

            <VCardText>
                <!-- Name: the term the entry is named by -->
                <VCombobox
                    v-model="form.title"
                    v-model:search="termSearch"
                    :items="termItems"
                    item-title="title"
                    item-value="value"
                    :return-object="false"
                    :label="$t('admin.taxonomies.dialog.name')"
                    :hint="$t('admin.taxonomies.dialog.nameHint')"
                    persistent-hint
                    :loading="loadingTerms"
                    :error-messages="firstError('title')"
                    variant="outlined"
                    density="comfortable"
                    class="mb-4"
                    autofocus
                >
                    <template #item="{ props: itemProps, item }">
                        <VListItem v-bind="itemProps" :title="undefined">
                            <VListItemTitle>{{ item.raw.title }}</VListItemTitle>
                            <VListItemSubtitle>
                                {{ item.raw.slug }}<template v-if="item.raw.taken"> — {{ $t('admin.taxonomies.dialog.alreadyUsed') }}</template>
                            </VListItemSubtitle>
                        </VListItem>
                    </template>
                </VCombobox>

                <!-- Vocabulary -->
                <VCombobox
                    v-model="form.taxonomy"
                    :items="vocabularyItems"
                    item-title="title"
                    item-value="value"
                    :return-object="false"
                    :label="$t('admin.taxonomies.dialog.vocabulary')"
                    :hint="$t('admin.taxonomies.dialog.vocabularyHint')"
                    persistent-hint
                    :error-messages="firstError('taxonomy')"
                    :readonly="isEdit"
                    variant="outlined"
                    density="comfortable"
                    class="mb-4"
                />

                <!-- Parent: the field this whole screen exists for -->
                <VAutocomplete
                    v-model="form.parent_id"
                    :items="parentItems"
                    item-title="title"
                    item-value="value"
                    :label="$t('admin.taxonomies.dialog.parent')"
                    :placeholder="$t('admin.taxonomies.dialog.noParent')"
                    :hint="$t('admin.taxonomies.dialog.parentHint')"
                    persistent-hint
                    :loading="loadingParents"
                    :error-messages="firstError('parent_id')"
                    :no-data-text="$t('admin.taxonomies.dialog.noParentOptions')"
                    clearable
                    variant="outlined"
                    density="comfortable"
                    class="mb-2"
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
                        <span v-if="item.raw.path?.length > 1">
                            <span class="text-medium-emphasis">{{ item.raw.path.slice(0, -1).join(' › ') }} › </span>{{ item.raw.title }}
                        </span>
                        <span v-else>{{ item.raw.title }}</span>
                    </template>
                </VAutocomplete>

                <VAlert
                    v-if="parentDropped"
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                    :text="$t('admin.taxonomies.dialog.parentCleared')"
                />

                <VRow class="mt-2">
                    <VCol cols="12" sm="4">
                        <VTextField
                            v-model="form.sort"
                            type="number"
                            min="0"
                            :label="$t('admin.taxonomies.dialog.position')"
                            :placeholder="$t('admin.taxonomies.dialog.positionAuto')"
                            :error-messages="firstError('sort')"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                        />
                    </VCol>
                    <VCol cols="12" sm="8" class="d-flex align-center ga-4">
                        <VSwitch
                            v-model="form.visible"
                            :label="$t('admin.taxonomies.dialog.visible')"
                            color="primary"
                            density="compact"
                            hide-details
                        />
                        <VSwitch
                            v-model="form.searchable"
                            :label="$t('admin.taxonomies.dialog.searchable')"
                            color="primary"
                            density="compact"
                            hide-details
                        />
                    </VCol>
                </VRow>

                <VTextarea
                    v-model="form.description"
                    :label="$t('admin.taxonomies.dialog.description')"
                    :error-messages="firstError('description')"
                    variant="outlined"
                    density="comfortable"
                    rows="2"
                    auto-grow
                    class="mt-4"
                />

                <!-- Feature flags live in `properties`; shown so it is clear
                     they survive a save here, but edited where they belong. -->
                <div v-if="node?.properties && Object.keys(node.properties).length" class="mt-2">
                    <div class="text-caption text-medium-emphasis mb-1">
                        {{ $t('admin.taxonomies.dialog.properties') }}
                    </div>
                    <VChip
                        v-for="(value, key) in node.properties"
                        :key="key"
                        size="x-small"
                        variant="tonal"
                        class="me-1 mb-1"
                    >
                        {{ key }}: {{ value === true ? 'yes' : (value === false ? 'no' : value) }}
                    </VChip>
                </div>
            </VCardText>

            <VDivider />

            <VCardActions>
                <VSpacer />
                <VBtn variant="text" :disabled="saving" @click="show = false">
                    {{ $t('common.cancel') }}
                </VBtn>
                <VBtn color="primary" variant="flat" :loading="saving" :disabled="saving" @click="save">
                    {{ isEdit ? $t('common.save') : $t('common.add') }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>
