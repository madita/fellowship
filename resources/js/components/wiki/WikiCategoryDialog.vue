<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog.js';

/**
 * Add a wiki category while writing a page.
 *
 * Filing a page used to be the only thing the editor could do: a category
 * that did not exist yet had to be invented by typing it into the category
 * field, which created it at the root with no way to say what it belongs
 * under, or by leaving the page for the category screens and coming back.
 *
 * The parent is the field this dialog is for. Each candidate is offered
 * with the path above it and indented by its depth, so "Characters" under
 * Lore and "Characters" under Rules can be told apart before the choice is
 * made rather than after.
 */

const { t } = useI18n();
const dialog = useDialog();

const emit = defineEmits(['update:modelValue', 'created']);

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** Prefilled name — whatever was typed into the category field. */
    name: { type: String, default: '' },
    /** Preselected parent, as a wiki category id. */
    parentId: { type: [Number, String], default: null },
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

const parentItems = computed(() => options.value.map((option) => ({
    value: option.id,
    title: option.title,
    depth: option.depth || 0,
    path: option.path || [option.title],
    pages: option.pages_count || 0,
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
        const { data } = await axios.get('/api/wiki/category-options');

        options.value = data?.data || [];
    } catch (error) {
        options.value = [];
        failed.value = true;
    } finally {
        loadingOptions.value = false;
    }
};

watch(() => [props.modelValue, props.name, props.parentId], () => {
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
        errors.value = { term: [t('wiki.categoryDialog.nameRequired')] };

        return;
    }

    saving.value = true;

    try {
        const { data } = await axios.post('/api/wiki/category', {
            term: title,
            taxonomy: 'wiki',
            parent_id: form.value.parent_id ?? null,
            content: form.value.description || null,
        });

        show.value = false;
        // The created category travels back in the shape the picker reads,
        // so the page being written can file itself under it straight away.
        emit('created', data?.category ?? { title });
        dialog.success(data?.message || t('wiki.categoryCreated'));
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data?.errors || {};
        } else {
            dialog.requestError(error, t('wiki.errorSavingCategory'));
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
                {{ $t('wiki.categoryDialog.title') }}
            </VCardTitle>

            <VDivider />

            <VCardText>
                <VTextField
                    v-model="form.title"
                    :label="$t('wiki.categoryDialog.name')"
                    :hint="$t('wiki.categoryDialog.nameHint')"
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
                    :text="$t('wiki.categoryDialog.nameTaken', { path: existing.path.join(' › ') })"
                />

                <!-- The parent, with the branch it sits in spelled out -->
                <VAutocomplete
                    v-model="form.parent_id"
                    :items="parentItems"
                    item-title="title"
                    item-value="value"
                    :label="$t('wiki.categoryDialog.parent')"
                    :placeholder="$t('wiki.categoryDialog.noParent')"
                    :hint="$t('wiki.categoryDialog.parentHint')"
                    persistent-hint
                    :loading="loadingOptions"
                    :error-messages="firstError('parent_id')"
                    :no-data-text="failed ? $t('wiki.categoryDialog.loadFailed') : $t('wiki.categoryDialog.noCategories')"
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
                    v-model="form.description"
                    :label="$t('wiki.categoryDialog.description')"
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
                    {{ $t('wiki.categoryDialog.create') }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>
