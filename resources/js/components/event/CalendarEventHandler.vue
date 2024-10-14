<script setup>
import {ref, watch, computed, nextTick, onMounted} from 'vue';
import {PerfectScrollbar} from 'vue3-perfect-scrollbar'
import {VForm} from 'vuetify/components/VForm'
import CustomDatePicker from "../common/CustomDatePicker.vue";
import UserAvatar from "../common/UserAvatar.vue";
import axios from "axios";
import {useCalendarStore} from '../../store/calendarStore.js'
import { isProxy, toRaw } from 'vue';

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
const eventTypeSelect = ref()
const calendarStore = useCalendarStore();

// const approval = ref(true)

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
const event = ref(props.event)
const localEventTypes = computed(() =>calendarStore.eventTypes)

// const localEventTypes = ref(props.eventTypes)
const eventDetails = ref();
const eventAnswers = ref();

if (isProxy(props.event)){
    event.value = toRaw(props.event)
}

// if (isProxy(props.eventTypes)){
//     localEventTypes.value = toRaw(props.eventTypes)
// }

// const eventType = ref()
// x => x.id === 1)
// const event = ref({...props.event})

const resetEvent = () => {
    event.value = JSON.parse(JSON.stringify(props.event))
    isStartDateValid.value = true;
    isEndDateValid.value = true;
    if(event.value.id > 0) {
        getEvent(event.value.id);
        //eventTypeSelect.value = Object.values(localEventTypes.value).find(item => item.id ===  event.value.type_id)
    }
    // console.log(eventDetails.value);

    nextTick(() => {
        refForm.value?.resetValidation()
    })
}

watch(() => props.event, (newEvent) => {
    event.value = {...newEvent};
    eventTypeSelect.value = event.value.type_id
    // eventTypeSelect.value = Object.values(localEventTypes.value).find(item => item.id ===  event.value.type_id)
}, {deep: true, immediate: true});

// watch(() => props.eventTypes, (eventTypes) => {
//     // ref(JSON.parse(JSON.stringify(props.eventTypes)))
//     localEventTypes.value = {...eventTypes};
//     // eventType.value = eventTypes.find(item => item.id === 1);
// }, {deep: true, immediate: true});

// watch(() => eventTypeSelect, (type) => {
//     console.log('watchtype',type)
// }, {deep: true, immediate: true});

// watch(() => props.editMode, (editMode) => {
//     localEditMode.value = editMode;
// }, {deep: true, immediate: true});
watch(() => props.editMode, () => {
    localEditMode.value = props.editMode
});

watch(() => props.isDrawerOpen, resetEvent)

const eventTypeItems = computed(() => {

    return Object.values(localEventTypes.value);
});


// evenType =
const eventType = computed(() => {
    let type = {}

    // if (localEventTypes.value && localEventTypes.value.length > 0) {

        type = Object.values(localEventTypes.value).find(item => item.name ===  event.value.extendedProps.type);
        // do something with result
    // }


    return type;
});



const eventTypeOptions = computed(() => {
    let type = {}

    // type = Object.values(localEventTypes.value).find(item => item.name ===  event.value.extendedProps.type);
    type = Object.values(localEventTypes.value).find(item => item.id ===  event.value.extendedProps.type_id);


    return JSON.parse(JSON.stringify(type.options));
});
//
// const eventAnswers = computed(() => {
//
//     let answers
// console.log('nnnnnnnnnnnnnnnnn',eventDetails.value.answers.length, eventDetails.value)
//     if(eventDetails.value.answers.length > 0 ) {
//         console.log('aaaaaaa', eventDetails)
//         console.log('dsfdsfdfdfss')
//
//         if(eventTypeOptions.guest && eventTypeOptions.guest.includes('approval')) {
//
//             for (const key in inputObject) {
//                 if (Array.isArray(inputObject[key])) {
//                     answers[key] = answers[key].filter(item => item.pivot && item.pivot.approved_at !== null);
//                 }
//             }
//
//
//             // console.log(eventTypeOptions.guest)
//             // answers = eventDetails.value.answers.filter((x) => {
//             //     return  x.pivot.approved_at!==null
//             //
//             // })
//         } else {
//             answers = eventDetails.value.answers
//         }
//
//
//         //
//         // return answers
//     }
//
//     return answers
//
//
// });

// const approval = computed(() => {
//
//     return Object.values(localEventTypes.value);
// });

// const maxDate = computed(() => {
//     const month = getMonth(new Date()) + 1 > 9 ? getMonth(new Date()) + 1 : `0${getMonth(new Date()) + 1}`;
//     return `${getYear(new Date())}-${month}-15T01:00:00Z`;
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

// const onYes = () => {
//     //Todo depending on type the awnser changes
//
//     joinEvent(event.value.id, 'going')
//     // Close drawer
//     // emit('update:isDrawerOpen', false)
//
// }
//
// // 👉 Form
// const onNo = () => {
//     // Close drawer
//     // emit('update:isDrawerOpen', false)
//     joinEvent(event.value.id, 'notgoing')
// }
//
// const onMaybe = () => {
//     // Close drawer
//     joinEvent(event.value.id, 'maybe')
// }

//todo fix isgoing
const  getIsGoing = (answer) => {
    //event.value.id > 0
    if (loadEventDetails.value) {
        return true;
    }

    // if(eventDetails.value.isGoing===null) {
    //     console.log('fdghdfjkgdhfgjkdfghjkfd')
    //     return true;
    // }

    if (eventDetails.value === null && typeof eventDetails.value != undefined) {
        return false;
    }

    return true;
    // return eventDetails.value.isGoing !== undefined && eventTypes.value.find(x => x.id === event.value.id).foo;.type === answer
    // return eventDetails.value.isGoing !== undefined && eventDetails.value.isGoing.type === answer
}

const joinEvent = (answer) => {
    // console.log('join', answer)
    // console.log('joinEvent', eventId, answer)
    //todo more options
    const eventId = event.value.id;

    // eventDetails.value.isGoing['type'] = answer;
    //todogetfulldate..days
    const userData = {
        answer: answer,
        bringing: 'stuff',
        days: '3'
    }

    axios.post(`/api/events/${eventId}/answer`, userData).then(() => {
        // this.page = {title: "", body: ""};
        // this.message = "Page saved ..link"
    }).catch((error) => {
        if (error.response.status === 422) {
            // this.creating.errors = error.response.data
            // this.editing.errors = error.response.data
        }
    })
}

const getEvent = async (eventId) => {
    loadEventDetails.value = true;
    // error.value = null; // Reset previous errors

    try {
        const response = await axios.get(`/api/events/${eventId}`);
        eventDetails.value = response.data; // Assuming the data is directly in the response
        //event.extendedProps.type = eventDetails.type_id

        eventTypeSelect.value = eventDetails.value.type_id
        // console.log('eventTypeSelect',eventTypeSelect)
        console.log('eventDetails',eventDetails)

        let answers =  response.data.answers;

        // eventAnswers.value = answers

        // console.log('eventTypeOptions',eventTypeOptions)

        if(eventTypeOptions.value.guest && eventTypeOptions.value.guest.includes('approval')) {

            console.log('test')

            for (const key in answers) {
                if (Array.isArray(answers[key])) {
                    answers[key] = answers[key].filter(item => item.pivot && item.pivot.approved_at !== null);
                }
            }

            // eventAnswers.value = answers;
            //
            // answers = [];


            // console.log(eventTypeOptions.guest)
            // answers = eventDetails.value.answers.filter((x) => {
            //     return  x.pivot.approved_at!==null
            //
            // })
        }

        eventAnswers.value = answers;
        answers = [];



    } catch (err) {
        console.error('Failed to load event details:', err);
        loadEventDetails.value = false;
        // error.value = 'Failed to load event details'; // Store error message
        eventDetails.value = null; // Reset event details on error
    } finally {
        loadEventDetails.value = false; // Ensure loading state is reset
    }
};

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
                v-if="event.id"
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

                            <VCol cols="12" v-if="eventTypeItems.length > 0">

                                <VSelect
                                    v-model="event.extendedProps.type_id"
                                    label="Type"
                                    placeholder="Select Event Label"
                                    :items="eventTypeItems"
                                    :item-title="item => item.name"
                                    :item-value="item => item.id"
                                >
                                    <template #selection="{ item }">
                                        <div
                                            class="align-center"
                                            :class="event.extendedProps.type ? 'd-flex' : ''"
                                        >
                                            <VIcon
                                                icon="mdi-circle-medium"
                                                :color="item.raw.color"
                                                class="me-2"
                                            />
                                            <span>{{ item.raw.name }}</span>
                                        </div>
                                    </template>

                                    <template #item="{ item, props: itemProps }">
                                        <VListItem v-bind="itemProps">
                                            <template #prepend>
                                                <VIcon
                                                    icon="mdi-circle-medium"
                                                    :color="item.raw.color"
                                                />
                                            </template>
                                        </VListItem>
                                    </template>
                                </VSelect>
                            </VCol>

                            <template v-if="event.extendedProps.type_id">

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
                                <VCol cols="12" v-show="eventTypeOptions.showAttributtes.includes('endDate')">
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
                                <VCol cols="12"  v-show="eventTypeOptions.showAttributtes.includes('allDay')">
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

                            </template>


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

            <!-- Event details to sign up -->
            <VCard flat v-else>
                <VCardText>
                    <!-- SECTION Form -->
                    <VForm

                    >
                        <VRow>
                            <!-- 👉 Title -->
                            <VCol cols="12">
<!--                                {{ // eventTypes }}-->

<!--                              <template if="eventTypes">  {{// eventTypes[event.id].options}}</template>-->
<!--{{localEventTypes}}gnaa-->

<!--{{// eventType.options}}-->
                                <div>Are you coming?</div>
                                <template v-if="eventType">

<!--                                    {{eventTypeOptions}}-->


                                    <VBtn v-for="(answer, value, index) in eventTypeOptions.answers"
                                          :key="`answer-${value}`"
                                          class="me-3"

                                          @click="joinEvent(value)">
                                        {{answer}}

                                    </VBtn>
                                </template>

<!--                                <VBtn-->
<!--                                    color="primary"-->
<!--                                    class="me-3"-->
<!--                                    :disabled="getIsGoing('going')"-->
<!--                                    @click="onYes"-->
<!--                                >-->
<!--                                    Yes-->
<!--                                </VBtn>-->
<!--                                <VBtn-->
<!--                                    variant="tonal"-->
<!--                                    color="primary"-->
<!--                                    class="me-3"-->
<!--                                    :disabled="getIsGoing('notgoing')"-->
<!--                                    @click="onNo"-->
<!--                                >-->
<!--                                    No-->
<!--                                </VBtn>-->
<!--                                <VBtn-->
<!--                                    variant="outlined"-->
<!--                                    color="secondary"-->
<!--                                    :disabled="getIsGoing('maybe')"-->
<!--                                    @click="onMaybe"-->
<!--                                >-->
<!--                                    Maybe-->
<!--                                </VBtn>-->
                            </VCol>

                            <VCol cols="12">
                                <label for="">Date/Time</label>
                                {{ event.start }} - {{ event.end }}
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
<!--                                        {{ eventDetails}}-->
<!--                                        {{ eventDetails.notgoing }}-->



                                            <template v-for="(answer, value) in eventAnswers">

                                                <v-list-subheader>{{value}} ({{ answer.length }})</v-list-subheader>
                                                <user-avatar v-for="user in answer" :key="`going-${user.id}`" :user="user"></user-avatar>
                                            </template>


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
