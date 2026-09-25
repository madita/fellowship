<template>
    <!-- Bind modelValue to the dialog's v-model -->
    <VDialog v-model="internalModelValue" max-width="600">
        <VCard>
            <VCardTitle class="text-h6">{{ $t('profileDialog.title') }}</VCardTitle>
            <VDivider />
            <VCardText>
                <!-- Carried over from an earlier event, so it is worth a
                     glance before it is sent -->
                <v-alert
                    v-if="prefilled"
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                    :text="$t('profileDialog.prefilled')"
                />

                <!-- Fix 1: Add v-if to ensure formData.days exists before rendering -->
                <v-select v-if="eventDays.length > 1 && formData.days"
                          v-model="formData.days"
                          :items="eventDays"
                          :label="$t('profileDialog.days')"
                          multiple
                />

                <template v-if="profileId > 0">
                    <template v-for="(field) in fields.form" :key="`field-${field.name}`">
                        <v-select
                            v-if="field.type==='select'"
                            :items="field.options"
                            item-title="value"
                            item-value="key"
                            v-model="formData[field.name]"
                            :label="field.label"
                        ></v-select>

                        <v-combobox
                            v-else-if="field.type==='taxonomy'"
                            v-model="formData[field.name]"
                            :items="taxonomieItems[field.name]"
                            item-title="title"
                            item-value="id"
                            :label="field.label"
                            chips
                            clearable
                            multiple
                            @focus ="getTerms(field.name, field.options)"
                        ></v-combobox>

                        <v-textarea
                            v-else-if="field.type==='textarea'"
                            :label="field.label"
                            :id="field.name"
                            v-model="formData[field.name]"
                        ></v-textarea>

                        <v-text-field
                            v-else
                            :label="field.label"
                            v-model="formData[field.name]"
                        ></v-text-field>
                    </template>
                </template>
            </VCardText>
            <VCardActions>
                <VSpacer/>
                <VBtn variant="text" :disabled="submitting" @click="cancel">{{ $t('common.cancel') }}</VBtn>
                <VBtn color="primary" variant="flat" :loading="submitting" :disabled="submitting" @click="confirm">
                    {{ $t('common.submit') }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<script setup>
import {ref, computed, watch} from 'vue';
import axios from "axios";
import { useDialog } from '@/composables/useDialog.js';

const dialog = useDialog();
const submitting = ref(false);
const selectedDays = ref();
const profile = ref();
const fields = ref({form: []}); // Fix 2: Initialize with default structure
const taxonomieItems = ref({});    // Fix 3: Initialize as object not array
const formData = ref({             // Fix 4: Initialize formData with days property
    days: []
});
// True once the form has been filled in from an earlier event, so the
// member is told to check it rather than trusting it blindly.
const prefilled = ref(false);

// Define the modelValue prop
const props = defineProps({
    modelValue: Boolean,
    event: Object,
    isGoing: Object,
    answer: String,
    resolve: {type: Function, required: true},
});

const localEvent = ref(null);
const localisGoing = ref(null);
const localAnswer = ref(props.answer);

const emit = defineEmits(['update:modelValue']);

// Create an internal computed property for modelValue
const internalModelValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

async function confirm() {
    if (submitting.value) return;
    const ok = await profileSubmit(localAnswer.value);
    if (!ok) return;
    props.resolve(true);
    internalModelValue.value = false;
}

function cancel() {
    if (submitting.value) return;
    props.resolve(false);
    internalModelValue.value = false;
}

// Sends the answer; resolves to true on success, false after showing the error.
const profileSubmit = async (answer) => {
    const data = formData.value;
    const params = {'answer': answer, 'data': data}

    submitting.value = true;
    try {
        await axios.post(`/api/events/${localEvent.value.id}/answer`, params);
        return true;
    } catch (error) {
        console.log(error);
        dialog.requestError(error);
        return false;
    } finally {
        submitting.value = false;
    }
}

const eventDays = computed(() => {
    if (!localEvent.value?.start || !localEvent.value?.end) return [];

    const start = new Date(localEvent.value.start);
    const end = new Date(localEvent.value.end);
    const days = [];

    const formatter = new Intl.DateTimeFormat("en-US", {weekday: "long"});

    while (start <= end) {
        days.push(formatter.format(new Date(start)));
        start.setDate(start.getDate() + 1);
    }

    return days;
});

const profileId = computed(() => {
    if (!localEvent.value?.extendedProps) return 0; // Fix 6: Return 0 instead of null
    return localEvent.value.extendedProps.event_profile_id || 0;
});

/**
 * Build the form: empty fields, then whatever we can fill in for the
 * member — their answer to this event if they have given one, otherwise
 * what they said the last time an event asked the same questions.
 *
 * The answers are applied here rather than in the isGoing watcher because
 * this runs across an await: anything that watcher set would be wiped by
 * the reset below.
 */
const profileForm = async () => {
    try {
        const response = await axios.get(`/api/datatable/event-profiles/${profileId.value}`);
        profile.value = response.data;
        fields.value = profile.value.options;

        const blank = {days: []};

        (fields.value?.form || []).forEach(field => {
            blank[field.name] = field.type === 'taxonomy' ? [] : '';
        });

        const answered = localisGoing.value?.profile;

        if (answered) {
            formData.value = {...blank, ...answered, days: answered.days || []};
            return;
        }

        formData.value = blank;
        await applyDraft();
    } catch (error) {
        console.log(error);
    }
}

/**
 * Carry over the member's last answers. A convenience, so a failure is
 * never worth reporting — the form simply stays empty.
 */
const applyDraft = async () => {
    try {
        const {data} = await axios.get(`/api/events/${localEvent.value.id}/profile-draft`);
        const draft = data?.data;

        if (!draft || !Object.keys(draft).length) return;

        Object.entries(draft).forEach(([name, value]) => {
            // Only fields this form actually has, and never the days
            if (name === 'days' || !(name in formData.value)) return;

            formData.value[name] = value;
        });

        prefilled.value = true;
    } catch (error) {
        console.log('No previous event profile to prefill from:', error);
    }
}

const getTerms = async (name, taxonomy) => {
    try {
        const response = await axios.get(`/api/tag/terms/${taxonomy}`);
        // Fix 9: Initialize object property if not exists
        if (!taxonomieItems.value[name]) {
            taxonomieItems.value[name] = [];
        }
        taxonomieItems.value[name] = response.data.terms;
    } catch (error) {
        console.log('error', error);
    }
}

// Kept in step before the event watcher builds the form, which reads it
watch(
    () => props.isGoing,
    (isGoing) => {
        localisGoing.value = isGoing;
    },
    {immediate: true}
);

watch(
    () => props.event,
    (newEvent) => {
        localEvent.value = newEvent ? JSON.parse(JSON.stringify(newEvent)) : null;
        prefilled.value = false;
        if (profileId.value > 0) {
            profileForm();
        }
    },
    {immediate: true}
);

watch(
    () => props.answer,
    (answer) => {
        localAnswer.value = answer;
    },
    {immediate: true}
);
</script>
