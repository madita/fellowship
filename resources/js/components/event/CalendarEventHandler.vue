<script setup>
import {ref, watch, computed, nextTick, onMounted} from 'vue';
import {PerfectScrollbar} from 'vue3-perfect-scrollbar'
import {VForm} from 'vuetify/components/VForm'
import {format} from "date-fns";
import VueDatePicker from "@vuepic/vue-datepicker";
import CustomDatePicker from "../common/CustomDatePicker.vue";
import UserAvatar from "../common/UserAvatar.vue";
import axios from "axios";
// import { useCalendarStore } from './useCalendarStore'
// import avatar1 from '@images/avatars/avatar-1.png'
// import avatar2 from '@images/avatars/avatar-2.png'
// import avatar3 from '@images/avatars/avatar-3.png'
// import avatar5 from '@images/avatars/avatar-5.png'
// import avatar6 from '@images/avatars/avatar-6.png'
// import avatar7 from '@images/avatars/avatar-7.png'

// 👉 store
const props = defineProps({
    isDrawerOpen: {
        type: Boolean,
        required: true,
    },
    editMode: {
        type: Boolean,
        required: true,
    },
    event: {},
})

const isFocused = ref(true)
const loadEventDetails = ref(true)

const emit = defineEmits([
    'update:isDrawerOpen',
    'addEvent',
    'updateEvent',
    'removeEvent',
])

const localEditMode = ref(props.editModed)

const title = ref()

// const store = useCalendarStore()
const refForm = ref()
// const availableCalendars = ref(['test', 'AA'])

// 👉 Event
// const event = ref({})
const event = ref(JSON.parse(JSON.stringify(props.event)))
const eventDetails = ref();
// const event = ref({...props.event})

const resetEvent = () => {
    event.value = JSON.parse(JSON.stringify(props.event))
    isStartDateValid.value = true;
    isEndDateValid.value = true;
    if(event.value.id > 0) {
        getEvent(event.value.id);
    }
    // console.log(eventDetails.value);

    // event.value = props.event
    //   console.log('props...',props.event)
    //   console.log('event...',event.value)
    //   console.log('eventttttttttttttt...',event.value.title)
    // event.value = {}
    nextTick(() => {
        refForm.value?.resetValidation()
    })
}

watch(() => props.event, (newEvent) => {
    event.value = {...newEvent};
}, {deep: true, immediate: true});

// watch(() => props.editMode, (editMode) => {
//     localEditMode.value = editMode;
// }, {deep: true, immediate: true});
watch(() => props.editMode, () => {
    localEditMode.value = props.editMode
});

watch(() => props.isDrawerOpen, resetEvent)

// watch(() => props.isDrawerOpen, (newValue) => {
//     if (newValue) {
//         nextTick(() => {
//             console.log('props.event', props.event)
//             event.value = {...props.event};  // reset the event data when drawer opens
//             console.log('watchevent', event.value)
//             console.log('watcheventtttt', event.value.title)
//             refForm.value?.resetValidation();
//         });
//     }
// });

const removeEvent = () => {
    emit('removeEvent', String(event.value.id))

    // Close drawer
    emit('update:isDrawerOpen', false)
}

const handleSubmit = () => {
    validateStartDate();
    validateEndDate();
    refForm.value?.validate().then(({valid}) => {
        if (valid) {
            localEditMode.value = false;

            // If id exist on id => Update event
            if ('id' in event.value)
                emit('updateEvent', event.value)

            // Else => add new event
            else
                emit('addEvent', event.value)

            // Close drawer
            emit('update:isDrawerOpen', false)
        }
    })
}

onMounted(() => {

    //console.log('eventonsidebar', event)
});

// 👉 Form
const onCancel = () => {
    // Close drawer
    emit('update:isDrawerOpen', false)
    localEditMode.value = false;
    nextTick(() => {
        refForm.value?.reset()
        resetEvent()
        refForm.value?.resetValidation()

        isStartDateValid.value = true;
        isEndDateValid.value = true;

    })
}

const onYes = () => {
    //Todo depending on type the awnser changes

    joinEvent(event.value.id, 'going')
    // Close drawer
    // emit('update:isDrawerOpen', false)

}

// 👉 Form
const onNo = () => {
    // Close drawer
    // emit('update:isDrawerOpen', false)
    joinEvent(event.value.id, 'notgoing')
}

const onMaybe = () => {
    // Close drawer
    joinEvent(event.value.id, 'maybe')
}

const  getIsGoing = (answer) => {
    //event.value.id > 0
    if (loadEventDetails.value) {
        return true;
    }

    if (eventDetails.value === null && typeof eventDetails.value != undefined) {
        return false;
    }
    return eventDetails.value.isGoing !== undefined && eventDetails.value.isGoing.type === answer
}

const joinEvent = (eventId, answer) => {
    // console.log('joinEvent', eventId, answer)
    eventDetails.value.isGoing.type = answer;
    //todogetfulldate..days
    const userData = {
        answer: answer,
        bringing: 'stuff',
        days: '3'
    }

    axios.post(`/api/events/${eventId}/going`, userData).then(() => {
        // this.page = {title: "", body: ""};
        // this.message = "Page saved ..link"
    }).catch((error) => {
        if (error.response.status === 422) {
            // this.creating.errors = error.response.data
            // this.editing.errors = error.response.data
        }
    })
}

// const getEvent = async (eventId) => {
//     try {
//         // loadEventDetails.value = true;
//         const response = await axios.get(`/api/events/${eventId}`);
//         const event = response.data
//         // loadEventDetails.value = false;
//         // console.log('fetchevents',events)
//         return event; // Returns the processed events array.
//     } catch (error) {
//         console.error("Error fetching event:", error);
//         return []; // Return an empty array in case of an error.
//     }
// };

const getEvent = async (eventId) => {
    loadEventDetails.value = true;
    // error.value = null; // Reset previous errors

    try {
        const response = await axios.get(`/api/events/${eventId}`);
        eventDetails.value = response.data; // Assuming the data is directly in the response
        console.log('Event details loaded:', eventDetails.value);
    } catch (err) {
        console.error('Failed to load event details:', err);
        // error.value = 'Failed to load event details'; // Store error message
        eventDetails.value = null; // Reset event details on error
    } finally {
        loadEventDetails.value = false; // Ensure loading state is reset
    }
};
//
// const getEvent = (eventId) => {
//     loadEventDetails.value = true;
//
//     return axios.get(`/api/events/${eventId}`).then((response) => {
//         console.log(response)
//         eventDetails.value = response.data
//         loadEventDetails.value = false;
//         console.log('eventDetails.value',eventDetails.value.going)
//         // this.eventData = response.data.data;
//         // this.event = response.data.data.event;
//
//         // this.isLoading = false
//     });
// }

const startDateTimePickerConfig = computed(() => {
    const config = {
        enableTime: !event.value.allDay,
        dateFormat: `Y-m-d${event.value.allDay ? '' : ' H:i'}`,
    }

    if (event.value.end)
        config.maxDate = event.value.end

    return config
})

const startDateError = ref(''); // Error message for start date
const endDateError = ref(''); // Error message for end date
const isStartDateValid = ref(true);
const isEndDateValid = ref(true);

const validateStartDate = () => {
    if (!event.value.start) {
        startDateError.value = 'Start date is required';
        isStartDateValid.value = false; // Set to false if validation fails
    } else {
        startDateError.value = '';
        isStartDateValid.value = true; // Set to true if validation passes
    }
};

const validateEndDate = () => {
    if (!event.value.end) {
        endDateError.value = 'End date is required';
        isEndDateValid.value = false;
        return false;
    } else {
        endDateError.value = '';
        isEndDateValid.value = true;
        return true;
    }
};

const endDateTimePickerConfig = computed(() => {
    const config = {
        enableTime: !event.value.allDay,
        dateFormat: `Y-m-d${event.value.allDay ? '' : ' H:i'}`,
    }

    if (event.value.start)
        config.minDate = event.value.start

    return config
})

const dialogModelValueUpdate = val => {
    emit('update:isDrawerOpen', val)
}

const rules = {
    title: [
        v => !!v || 'Title is required', // Ensures the title is not empty
    ],
    date: [
        v => !!v || 'Date is required', // Ensures the date is not empty
    ],
    location: [
        v => !!v || 'Location is required', // Ensures the location is not empty
    ],
}

// onMounted( () => {
//     title.value = event.value.id ? 'Update Event' : 'Add Event'
// });
</script>

<template>
    <VNavigationDrawer
        temporary
        location="end"
        :model-value="props.isDrawerOpen"
        width="420"
        class="scrollable-content"
        @update:model-value="dialogModelValueUpdate"
    >

        <div class="pa-2 d-flex align-center" v-if="localEditMode">
            <h5 class="text-h5 me-3">
                <template v-if="event.id">Update Event</template>
                <template v-else>Add Event</template>
            </h5>

            <VSpacer/>

            <slot name="beforeClose"/>
            <VBtn
                color="primary"
                class="me-3"
                @click="localEditMode = false"
            >View
            </VBtn>

            <!--          <IconBtn-->
            <!--              class="text-medium-emphasis"-->
            <!--              size="x-small"-->
            <!--              @click="$emit('cancel', $event)"-->
            <!--          >-->
            <!--              <VIcon-->
            <!--                  icon="ri-close-line"-->
            <!--                  size="24"-->
            <!--              />-->
            <!--          </IconBtn>-->
        </div>
        <div class="pa-2 d-flex align-center" v-else>
            <h5 class="text-h5 me-3">

                {{ event.title }}
            </h5>


            <VSpacer/>
            <slot name="beforeClose"/>
            <VBtn
                color="primary"
                class="me-3"
                @click="localEditMode = true"
            >Edit
            </VBtn>
        </div>

        <VDivider/>

        <PerfectScrollbar :options="{ wheelPropagation: false }">
            <VCard flat v-if="localEditMode">
                <VCardText>
                    <!-- SECTION Form -->
                    <VForm
                        ref="refForm"
                        @submit.prevent="handleSubmit"
                    >
                        <VRow>
                            <!-- 👉 Title -->
                            <VCol cols="12">
                                <VTextField
                                    v-model="event.title"
                                    label="Title"
                                    :rules="rules.title"
                                    placeholder="Title"
                                />
                            </VCol>


                            <!-- 👉 Start date -->
                            <VCol cols="12">
                                <!--                                <div class="vuedatepicker-wrapper">-->
                                <!--                                    &lt;!&ndash; Label &ndash;&gt;-->
                                <!--                                    <label class="v-label v-field-label">Start Date</label>-->
                                <!--                                    &lt;!&ndash; Date Picker &ndash;&gt;-->
                                <!--                                <VueDatePicker-->
                                <!--                                    class="datepicker-input"-->
                                <!--                                    locale="de"-->
                                <!--                                    v-model="event.start"-->
                                <!--                                    :class="{ 'is-focused': isFocused }"-->
                                <!--                                    @focus="isFocused = true"-->
                                <!--                                    @blur="isFocused = false"-->
                                <!--                                    :enable-time-picker="!event.allDay"-->
                                <!--                                    minutes-increment="15"-->
                                <!--                                    :rules="rules.date"-->
                                <!--                                    utc-->
                                <!--                                    auto-apply-->
                                <!--                                    :preview-format="format"-->
                                <!--                                />-->
                                <!--&lt;!&ndash;                                <span v-if="!isStartDateValid" class="error-message">{{ startDateError }}</span>&ndash;&gt;-->
                                <!--                                </div>-->
                                <CustomDatePicker
                                    label="Start Date"
                                    v-model="event.start"
                                    :allDay="event.allDay"
                                    :error="!isStartDateValid"
                                    :error-messages="['Date is required']"
                                    :date-picker-config="{ enableTimePicker: true }"
                                />
                            </VCol>

                            <!-- 👉 End date -->
                            <VCol cols="12">
                                <!--                <AppDateTimePicker-->
                                <!--                  :key="JSON.stringify(endDateTimePickerConfig)"-->
                                <!--                  v-model="event.end"-->

                                <!--                  label="End date"-->
                                <!--                  placeholder="Select End Date"-->
                                <!--                  :config="endDateTimePickerConfig"-->
                                <!--                />-->
                                <!--                                <custom-date-picker-->
                                <!--                                    label="Event Date"-->
                                <!--                                    v-model="event.end"-->
                                <!--                                    id="event-date"-->
                                <!--                                    :error="isStartDateValid ? 'Please select a valid date':''"-->
                                <!--                                />-->
                                <CustomDatePicker
                                    label="End Date"
                                    v-model="event.end"
                                    :allDay="event.allDay"
                                    :error="!isEndDateValid"
                                    :error-messages="['Date is required']"
                                    :date-picker-config="{ enableTimePicker: true }"
                                />
                                <!--                                <VueDatePicker-->
                                <!--                                    locale="de"-->
                                <!--                                    v-model="event.end"-->
                                <!--                                    :class="{ 'error-class': !isStartDateValid }"-->
                                <!--                                    :enable-time-picker="!event.allDay"-->
                                <!--                                    minutes-increment="15"-->
                                <!--                                    @blur="validateEndDate"-->
                                <!--                                    utc-->
                                <!--                                    auto-apply-->
                                <!--                                    :preview-format="format"-->
                                <!--                                />-->
                                <!--                                <span v-if="!isEndDateValid" class="error-message">{{ endDateError }}</span>-->
                            </VCol>

                            <!-- 👉 All day -->
                            <VCol cols="12">
                                <VSwitch
                                    color="primary"
                                    v-model="event.allDay"
                                    label="All day"
                                />
                            </VCol>

                            <!-- 👉 Location -->
                            <VCol cols="12">
                                <VTextField
                                    v-model="event.extendedProps.location"
                                    :rules="rules.location"
                                    label="Location"
                                    placeholder="Meeting room"
                                />
                            </VCol>

                            <!-- 👉 Description -->
                            <VCol cols="12">
                                <VTextarea
                                    v-model="event.extendedProps.description"
                                    label="Description"
                                    placeholder="Meeting description"
                                />
                            </VCol>

                            <!-- 👉 Form buttons -->
                            <VCol cols="12">
                                <VBtn
                                    type="submit"
                                    class="me-3"
                                >
                                    Submit
                                </VBtn>
                                <VBtn
                                    variant="outlined"
                                    color="secondary"
                                    @click="onCancel"
                                >
                                    Cancel
                                </VBtn>
                            </VCol>
                        </VRow>
                    </VForm>
                    <!-- !SECTION -->
                </VCardText>
            </VCard>

            <VCard flat v-else>
                <VCardText>
                    <!-- SECTION Form -->
                    <VForm

                    >
                        <VRow>
                            <!-- 👉 Title -->
                            <VCol cols="12">
                                <div>Are you coming?</div>
                                <VBtn
                                    color="primary"
                                    class="me-3"
                                    :disabled="getIsGoing('going')"
                                    @click="onYes"
                                >
                                    Yes
                                </VBtn>
                                <VBtn
                                    variant="tonal"
                                    color="primary"
                                    class="me-3"
                                    :disabled="getIsGoing('notgoing')"
                                    @click="onNo"
                                >
                                    No
                                </VBtn>
                                <VBtn
                                    variant="outlined"
                                    color="secondary"
                                    :disabled="getIsGoing('maybe')"
                                    @click="onMaybe"
                                >
                                    Maybe
                                </VBtn>
                            </VCol>

                            <!-- 👉 Location -->
                            <VCol cols="12">
                                <label for="">Location</label>
                                {{ event.extendedProps.location }}
                            </VCol>

                            <!-- 👉 Description -->
                            <VCol cols="12">
                                <label for="">Description</label>
                                <div v-html="event.extendedProps.description" ></div>

                            </VCol>

                            <!-- 👉 Form buttons -->
                            <VCol cols="12">

<!--                                    <div @click="getEvent(event.id)">Load Event Details</div>-->
                                    <div v-if="loadEventDetails">Loading...</div>
                                    <div v-else>
<!--                                        {{ eventDetails.notgoing }}-->

                                <v-list-subheader>Is Going ({{ eventDetails.going.length }})</v-list-subheader>
                                <user-avatar v-for="user in eventDetails.going" :key="`going-${user.id}`" :user="user"></user-avatar>
                                <v-list-subheader>Maybe Going ({{ eventDetails.maybe.length }})</v-list-subheader>
                                <user-avatar v-for="user in eventDetails.maybe" :key="`maybe-${user.id}`" :user="user"></user-avatar>
                                <v-list-subheader>Not Going ({{ eventDetails.notgoing.length }})</v-list-subheader>
                                <user-avatar v-for="user in eventDetails.notgoing" :key="`notgoing-${user.id}`" :user="user"></user-avatar>
                                    </div>
                            </VCol>
                        </VRow>
                    </VForm>
                    <!-- !SECTION -->
                </VCardText>
            </VCard>
        </PerfectScrollbar>
    </VNavigationDrawer>
</template>
<style>




</style>
