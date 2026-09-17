<script setup>
import { ref, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useTicketHelpers } from '@/composables/useTicketHelpers.js';

/**
 * Type, title, priority and description of a ticket — used by the create
 * dialog and by the inline edit on the ticket page. The parent does the
 * request; `saving` shows its progress.
 */
const props = defineProps({
    // Initial values: { ticket_type_id, title, priority, description }
    ticket: { type: Object, default: () => ({}) },
    saving: { type: Boolean, default: false },
    submitLabel: { type: String, default: '' },
});

const emit = defineEmits(['submit', 'cancel']);

const { t } = useI18n();
const { priorityFilterOptions } = useTicketHelpers();

const formRef = ref(null);
const types = ref([]);
const form = ref({});

watch(() => props.ticket, (ticket) => {
    form.value = {
        ticket_type_id: ticket.ticket_type_id ?? null,
        title: ticket.title ?? '',
        priority: ticket.priority ?? 'normal',
        description: ticket.description ?? '',
    };
}, { immediate: true });

const submit = async () => {
    if (props.saving) return;
    const { valid } = await formRef.value.validate();
    if (valid) emit('submit', { ...form.value });
};

onMounted(async () => {
    try {
        const response = await axios.get('/api/ticket-types');
        types.value = response.data;
    } catch (err) {
        console.error('Failed to load ticket types:', err);
    }
});
</script>

<template>
    <v-form ref="formRef" @submit.prevent="submit">
        <v-row dense>
            <v-col cols="12" sm="7">
                <v-select
                    v-model="form.ticket_type_id"
                    :items="types"
                    item-title="name"
                    item-value="id"
                    :label="t('tickets.fields.ticketType')"
                    :rules="[v => !!v || t('tickets.validation.typeRequired')]"
                >
                    <template #item="{ item, props: itemProps }">
                        <v-list-item v-bind="itemProps">
                            <template #prepend>
                                <v-icon :color="item.raw.color" :icon="item.raw.icon || 'mdi-ticket-outline'" />
                            </template>
                        </v-list-item>
                    </template>
                </v-select>
            </v-col>
            <v-col cols="12" sm="5">
                <v-select
                    v-model="form.priority"
                    :items="priorityFilterOptions(false)"
                    item-title="label"
                    item-value="value"
                    :label="t('tickets.fields.priority')"
                >
                    <template #item="{ item, props: itemProps }">
                        <v-list-item v-bind="itemProps">
                            <template #prepend>
                                <v-icon :color="item.raw.color" :icon="item.raw.icon" />
                            </template>
                        </v-list-item>
                    </template>
                </v-select>
            </v-col>
            <v-col cols="12">
                <v-text-field
                    v-model="form.title"
                    :label="t('tickets.fields.title')"
                    :rules="[v => !!v?.trim() || t('tickets.validation.titleRequired')]"
                    counter="255"
                    maxlength="255"
                />
            </v-col>
            <v-col cols="12">
                <v-textarea v-model="form.description" :label="t('tickets.fields.description')" rows="6" auto-grow />
            </v-col>
        </v-row>
        <div class="d-flex justify-end ga-2">
            <v-btn variant="text" :disabled="saving" @click="emit('cancel')">{{ t('tickets.cancel') }}</v-btn>
            <v-btn type="submit" color="primary" variant="flat" :loading="saving">
                {{ submitLabel || t('tickets.update') }}
            </v-btn>
        </div>
    </v-form>
</template>
