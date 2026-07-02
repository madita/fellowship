<template>
    <v-dialog
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        max-width="700"
        transition="slide-y-transition"
    >
        <v-card>
            <v-card-title>
                {{ item?.id ? t('menuAdmin.editItem') : t('menuAdmin.createItem') }}
            </v-card-title>

            <v-card-text>
                <v-form ref="formRef" v-model="valid">
                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.label"
                                :label="t('menuAdmin.label')"
                                :rules="[required]"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.type"
                                :items="typeOptions"
                                :label="t('menuAdmin.type')"
                                :rules="[required]"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="8">
                            <v-text-field
                                v-if="form.type === 'route'"
                                v-model="form.route"
                                :label="t('menuAdmin.routePath')"
                                :rules="[required]"
                                variant="outlined"
                                density="compact"
                                placeholder="/dashboard"
                            />
                            <v-text-field
                                v-else
                                v-model="form.url"
                                :label="t('menuAdmin.url')"
                                :rules="[required]"
                                variant="outlined"
                                density="compact"
                                placeholder="https://example.com"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.icon"
                                :label="t('menuAdmin.icon')"
                                variant="outlined"
                                density="compact"
                                placeholder="mdi-home"
                                :hint="t('menuAdmin.iconHint')"
                                persistent-hint
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.metadata.description"
                                :label="t('menuAdmin.itemDescription')"
                                variant="outlined"
                                density="compact"
                                rows="2"
                                auto-grow
                                :hint="t('menuAdmin.itemDescriptionHint')"
                                persistent-hint
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.parent_id"
                                :items="parentOptions"
                                item-title="label"
                                item-value="id"
                                :label="t('menuAdmin.parent')"
                                variant="outlined"
                                density="compact"
                                clearable
                                :hint="t('menuAdmin.parentHint')"
                                persistent-hint
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.target"
                                :items="targetOptions"
                                :label="t('menuAdmin.target')"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-divider class="my-2" />
                            <p class="text-subtitle-2 mb-2">{{ t('menuAdmin.visibility') }}</p>
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-switch
                                v-model="form.auth_required"
                                :label="t('menuAdmin.authRequired')"
                                color="primary"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-switch
                                v-model="form.guest_only"
                                :label="t('menuAdmin.guestOnly')"
                                color="primary"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-switch
                                v-model="form.is_active"
                                :label="t('menuAdmin.active')"
                                color="primary"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.role"
                                :label="t('menuAdmin.role')"
                                variant="outlined"
                                density="compact"
                                clearable
                                placeholder="admin"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.permission"
                                :label="t('menuAdmin.permission')"
                                variant="outlined"
                                density="compact"
                                clearable
                                placeholder="manage-users"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-text-field
                                v-model.number="form.order"
                                :label="t('menuAdmin.order')"
                                type="number"
                                variant="outlined"
                                density="compact"
                                :hint="t('menuAdmin.orderHint')"
                                persistent-hint
                            />
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>

            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="$emit('update:modelValue', false)">
                    {{ t('common.cancel') }}
                </v-btn>
                <v-btn
                    color="primary"
                    variant="tonal"
                    :loading="saving"
                    :disabled="!valid"
                    @click="save"
                >
                    {{ t('common.save') }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    item: { type: Object, default: null },
    menuId: { type: [Number, String], default: null },
    items: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'saved', 'error']);

const { t } = useI18n();

const formRef = ref(null);
const valid = ref(false);
const saving = ref(false);

const blank = () => ({
    label: '',
    type: 'route',
    url: '',
    route: '',
    icon: '',
    parent_id: null,
    target: '_self',
    role: '',
    permission: '',
    auth_required: false,
    guest_only: false,
    order: 0,
    is_active: true,
    metadata: { description: '' },
});

const form = ref(blank());

const required = (v) => v !== null && v !== undefined && v !== '' || t('validation.required');

const typeOptions = computed(() => [
    { title: t('menuAdmin.types.route'),    value: 'route' },
    { title: t('menuAdmin.types.custom'),   value: 'custom' },
    { title: t('menuAdmin.types.external'), value: 'external' },
    { title: t('menuAdmin.types.page'),     value: 'page' },
]);

const targetOptions = computed(() => [
    { title: t('menuAdmin.targets.self'),  value: '_self' },
    { title: t('menuAdmin.targets.blank'), value: '_blank' },
]);

// Flatten the tree so deeper parents are selectable too, with indented labels.
const parentOptions = computed(() => {
    const flat = [];
    const visit = (nodes, depth) => {
        for (const node of nodes) {
            if (node.id === props.item?.id) continue; // can't parent to self
            flat.push({
                id: node.id,
                label: `${'— '.repeat(depth)}${node.label}`,
            });
            if (node.children?.length) visit(node.children, depth + 1);
        }
    };
    visit(props.items, 0);
    return flat;
});

watch(
    () => props.item,
    (val) => {
        form.value = val?.id ? { ...blank(), ...val } : blank();
        // metadata may arrive as null from the API — keep it an object so the
        // description field can bind safely.
        if (!form.value.metadata || typeof form.value.metadata !== 'object') {
            form.value.metadata = { description: '' };
        }
    },
    { immediate: true },
);

watch(
    () => props.modelValue,
    (open) => {
        if (!open) {
            form.value = blank();
            formRef.value?.resetValidation();
        } else if (!props.item?.id) {
            // Default new items to the end of the list.
            form.value.order = props.items.length;
        }
    },
);

async function save() {
    const { valid: ok } = await formRef.value.validate();
    if (!ok) return;

    saving.value = true;
    try {
        if (props.item?.id) {
            await axios.patch(`/api/admin/menu-items/${props.item.id}`, form.value);
        } else {
            await axios.post(`/api/admin/menus/${props.menuId}/items`, form.value);
        }
        emit('saved');
    } catch (error) {
        console.error('Error saving menu item:', error);
        emit('error', { source: 'itemSave', error });
    } finally {
        saving.value = false;
    }
}
</script>
