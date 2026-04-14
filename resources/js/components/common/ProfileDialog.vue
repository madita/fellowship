<template>
    <!-- Bind modelValue to the dialog's v-model -->
    <VDialog v-model="internalModelValue" max-width="400">
        <VCard>
            <VCardText>
                <!-- Fix 1: Add v-if to ensure formData.days exists before rendering -->
                <v-select v-if="eventDays.length > 1 && formData.days"
                          v-model="formData.days"
                          :items="eventDays"
                          :label="$t('profileDialog.days')"
                          outlined
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
                <VBtn color="grey" @click="cancel">{{ $t('common.cancel') }}</VBtn>
                <VBtn color="red" @click="confirm">
                    {{ $t('common.submit') }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<script setup>
import {ref, computed, watch} from 'vue';
import axios from "axios";

const selectedDays = ref();
const profile = ref();
const fields = ref({form: []}); // Fix 2: Initialize with default structure
const taxonomieItems = ref({});    // Fix 3: Initialize as object not array
const formData = ref({             // Fix 4: Initialize formData with days property
    days: []
});

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

function confirm() {
    props.resolve(true);
    internalModelValue.value = false;
    profileSubmit(localAnswer.value);
}

function cancel() {
    props.resolve(false);
    internalModelValue.value = false;
}

const profileSubmit = async (answer) => {
    const data = formData.value;
    const params = {'answer': answer, 'data': data}

    try {
        await axios.post(`/api/events/${localEvent.value.id}/answer`, params);
        // Handle success if needed
    } catch (error) {
        console.log(error);
        if (error.response && error.response.status === 422) {
            this.editing.errors = error.response.data;
        }
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

const profileForm = async () => {
    try {
        const response = await axios.get(`/api/datatable/event-profiles/${profileId.value}`);
        profile.value = response.data;
        fields.value = profile.value.options;

        // Fix 7: Create formData object correctly with days array
        // Instead of trying to spread the form fields (which is an array, not an object)
        formData.value = {days: []};

        // Fix 8: Properly initialize form fields
        if (fields.value && fields.value.form) {
            fields.value.form.forEach(field => {
                // Initialize each field with appropriate default value
                if (field.type === 'taxonomy') {
                    formData.value[field.name] = [];
                } else {
                    formData.value[field.name] = '';
                }
            });
        }
    } catch (error) {
        console.log(error);
        if (error.response && error.response.status === 422) {
            this.editing.errors = error.response.data;
        }
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

watch(
    () => props.event,
    (newEvent) => {
        localEvent.value = newEvent ? JSON.parse(JSON.stringify(newEvent)) : null;
        if (profileId.value > 0) {
            profileForm();
        }
    },
    {immediate: true}
);

watch(
    () => props.isGoing,
    (isGoing) => {
        localisGoing.value = isGoing;
        if (isGoing && isGoing.profile) {
            // Fix 10: Ensure days property exists
            const profile = isGoing.profile || {};
            formData.value = {
                ...profile,
                days: profile.days || []
            };
        }
    },
    {immediate: true} // Fix 11: Added immediate: true to initialize correctly
);

watch(
    () => props.answer,
    (answer) => {
        localAnswer.value = answer;
    },
    {immediate: true}
);
</script>
