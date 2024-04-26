<script setup>
import {ref, watch, computed, nextTick, onMounted} from 'vue';
import {PerfectScrollbar} from 'vue3-perfect-scrollbar'
import {VForm} from 'vuetify/components/VForm'
import {format} from "date-fns";
import VueDatePicker from "@vuepic/vue-datepicker";
import CustomDatePicker from "../common/CustomDatePicker.vue";
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
    event: {},
})

const isFocused = ref(true)

const emit = defineEmits([
    'update:isDrawerOpen',
    'addEvent',
    'updateEvent',
    'removeEvent',
])

const title = ref()

// const store = useCalendarStore()
const refForm = ref()
// const availableCalendars = ref(['test', 'AA'])

// 👉 Event
// const event = ref({})
const event = ref(JSON.parse(JSON.stringify(props.event)))
// const event = ref({...props.event})

const resetEvent = () => {
    event.value = JSON.parse(JSON.stringify(props.event))
    isStartDateValid.value = true;
    isEndDateValid.value = true;
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
    nextTick(() => {
        refForm.value?.reset()
        resetEvent()
        refForm.value?.resetValidation()

        isStartDateValid.value = true;
        isEndDateValid.value = true;

    })
}

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

        <div class="pa-2 d-flex align-center">
            <h5 class="text-h5">
                <template v-if="event.id">Update Event</template>
                <template v-else>Add Event</template>
            </h5>
            <VSpacer/>

            <slot name="beforeClose"/>

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

        <VDivider/>

        <PerfectScrollbar :options="{ wheelPropagation: false }">
            <VCard flat>
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
        </PerfectScrollbar>
    </VNavigationDrawer>
</template>
<style>




</style>
