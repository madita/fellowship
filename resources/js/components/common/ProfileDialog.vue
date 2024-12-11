<template>
    <!-- Bind modelValue to the dialog's v-model -->
    <VDialog v-model="internalModelValue" max-width="400">


        <VCard>
            <!--            <VCardTitle class="text-h5">{{ title || 'Are you sure?' }}</VCardTitle>-->
            <VCardText>

                <v-select v-if="eventDays.length>1"
                          v-model="selectedDays"
                          :items="eventDays"
                          label="Days"
                          outlined
                          multiple
                />

                <template v-if="profileId > 0">
                    <v-row v-for="(field, name) in fields.form" :key="`field-${field.name}`">
                        <v-col
                            cols="12"
                        >


                            <v-select
                                v-if="field.type==='select'"
                                :items="field.options"
                                item-title="value"
                                item-value="key"
                                v-model="formData[name]"
                                :label="field.label"
                            ></v-select>

                            <v-select
                                v-else-if="field.type==='taxonomy'"
                                :items="getTaxonomy()"
                                item-title="value"
                                item-value="id"
                                v-model="formData[name]"
                                :label="field.label"
                            ></v-select>

                            <v-textarea
                                v-else-if="field.type==='textarea'"
                                :label="column"
                                :id="column"
                                v-model="formData[name]"
                                :value="formData[name]"
                            ></v-textarea>

                            <v-text-field
                                v-else
                                :label="field.label"
                                v-model="formData[name]"
                                :value="formData[name]"
                            ></v-text-field>

                        </v-col>
                    </v-row>

                </template>


            </VCardText>
            <VCardActions>
                <VSpacer/>
                <VBtn color="grey" @click="cancel">Cancel</VBtn>
                <VBtn color="red" @click="confirm">
                    Submit
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
const fields = ref();
const formData = ref([]);

// Define the modelValue prop
const props = defineProps({
    modelValue: Boolean, // Highlight: Bind modelValue to control dialog visibility
    event: Object,
    resolve: {type: Function, required: true},
});

const localEvent = ref(null);


const emit = defineEmits(['update:modelValue']); // Highlight: Emit update for modelValue
const textField = ref('');

// Create an internal computed property for modelValue
const internalModelValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value), // Highlight: Emit changes
});

function confirm() {
    props.resolve(true);
    internalModelValue.value = false;
}

function cancel() {
    props.resolve(false);
    internalModelValue.value = false;
}

//
// // Disable the confirmation button if keyword is required but not matched
// const confirmationButtonDisabled = computed(() => {
//     return props.confirmationKeyword && props.confirmationKeyword !== textField.value;
// });

const eventDays = computed(() => {
    // console.log('profile',localEvent, props.event)
    if (!localEvent.value.start || !localEvent.value.end) return [];

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

    if (!localEvent.value.extendedProps) return null;

    return localEvent.value.extendedProps.event_profile_id;
});

const profileForm = async () => {

    console.log('profileform', localEvent)

    await axios.get(`/api/datatable/event-profiles/${profileId.value}`).then((response) => {

        profile.value = response.data

        // fields.value = JSON.parse(profile.value.options)
        fields.value = profile.value.options


        // this.page = {title: "", body: ""};
        // this.message = "Page saved ..link"
    }).catch((error) => {
        console.log(error)
        if (error.response.status === 422) {
            // this.creating.errors = error.response.data
            this.editing.errors = error.response.data
        }
    })
}

const getTaxonomy = async () => {
    return [];

    // console.log('profileform', localEvent)
    //
    // await axios.get(`/api/datatable/event-profiles/${profileId.value}`).then((response) => {
    //
    //     profile.value = response.data
    //
    //     // fields.value = JSON.parse(profile.value.options)
    //     fields.value = profile.value.options
    //
    //
    //     // this.page = {title: "", body: ""};
    //     // this.message = "Page saved ..link"
    // }).catch((error) => {
    //     console.log(error)
    //     if (error.response.status === 422) {
    //         // this.creating.errors = error.response.data
    //         this.editing.errors = error.response.data
    //     }
    // });
}

// const profileForm = async (() => {
//     try {
//
//         const response = await axios.get(`/api/datatable/event-profiles/${localEvent.extendedProps.event_profile_id}`);
//
//         profile.value = response.data;
//
//
//     } catch (err) {
//         console.error('Failed to load event details:', err);
//     } finally {
//         loadEventDetails.value = false;
//     }
//
// });
// onMounted(profileForm);

watch(
    () => props.event,
    (newEvent) => {
        console.log('profilewatch', profileId)
        localEvent.value = newEvent ? JSON.parse(JSON.stringify(newEvent)) : null;
        if (profileId.value > 0) {
            console.log('test')
            profileForm()
        }
    },
    {immediate: true} // Trigger immediately to initialize localEvent
);

</script>
