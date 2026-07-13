<template>
    <v-dialog
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        max-width="600"
        transition="slide-y-transition"
    >
        <v-card>
            <v-card-title>
                {{ menu?.id ? t('menuAdmin.editMenu') : t('menuAdmin.createMenu') }}
            </v-card-title>

            <v-card-text>
                <v-form ref="formRef" v-model="valid">
                    <v-text-field
                        v-model="form.name"
                        :label="t('menuAdmin.name')"
                        :rules="[required]"
                        variant="outlined"
                        density="compact"
                    />

                    <v-text-field
                        v-model="form.slug"
                        :label="t('menuAdmin.slug')"
                        :rules="[required]"
                        variant="outlined"
                        density="compact"
                        :hint="t('menuAdmin.slugHint')"
                        persistent-hint
                    />

                    <v-select
                        v-model="form.location"
                        :items="locationOptions"
                        :label="t('menuAdmin.location')"
                        variant="outlined"
                        density="compact"
                        clearable
                        :hint="t('menuAdmin.locationHint')"
                        persistent-hint
                        class="mt-3"
                    />

                    <v-textarea
                        v-model="form.description"
                        :label="t('menuAdmin.description')"
                        variant="outlined"
                        density="compact"
                        rows="2"
                        class="mt-3"
                    />

                    <v-switch
                        v-model="form.is_active"
                        :label="t('menuAdmin.active')"
                        color="primary"
                        hide-details
                    />
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
    menu: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue', 'saved', 'error']);

const { t } = useI18n();

const formRef = ref(null);
const valid = ref(false);
const saving = ref(false);

const blank = () => ({
    name: '',
    slug: '',
    location: '',
    description: '',
    is_active: true,
});

const form = ref(blank());

const required = (v) => !!v || t('validation.required');

const locationOptions = computed(() => [
    { title: t('menuAdmin.locations.header'),  value: 'header' },
    { title: t('menuAdmin.locations.footer'),  value: 'footer' },
    { title: t('menuAdmin.locations.mobile'),  value: 'mobile' },
    { title: t('menuAdmin.locations.sidebar'), value: 'sidebar' },
]);

watch(
    () => props.menu,
    (val) => {
        form.value = val?.id ? { ...blank(), ...val } : blank();
    },
    { immediate: true },
);

watch(
    () => props.modelValue,
    (open) => {
        if (!open) {
            form.value = blank();
            formRef.value?.resetValidation();
        }
    },
);

async function save() {
    const { valid: ok } = await formRef.value.validate();
    if (!ok) return;

    saving.value = true;
    try {
        if (props.menu?.id) {
            await axios.patch(`/api/admin/menus/${props.menu.id}`, form.value);
        } else {
            await axios.post('/api/admin/menus', form.value);
        }
        emit('saved');
    } catch (error) {
        console.error('Error saving menu:', error);
        emit('error', { source: 'menuSave', error });
    } finally {
        saving.value = false;
    }
}
</script>
