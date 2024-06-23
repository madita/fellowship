<script setup>

import { ref, watch, computed, onMounted } from 'vue';
import axios from 'axios';
import { VCalendar } from 'vuetify/labs/VCalendar'
import {getDate, format, formatDistanceToNow} from "date-fns";

// Sample API call to fetch events (update the URL as per your backend)
// const fetchEvents = () => axios.get('/api/events').then((res) => res.data.data.events);

// import { defineComponent } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'

import customViewPlugin from './custom-list-view.js';
import CalendarEventHandler from "./CalendarEventHandler.vue";
// import CalendarEventHandlerForm from "./CalendarEventHandlerForm.vue";
import VueDatePicker from "@vuepic/vue-datepicker";

import {eventBus} from "../common/eventBus.js";

const blankEvent = {
    title: '',
    start: '',
    end: '',
    allDay: false,
    url: '',
    extendedProps: {
        /*
              ℹ️ We have to use undefined here because if we have blank string as value then select placeholder will be active (moved to top).
              Hence, we need to set it to undefined or null
            */
        calendar: undefined,
        guests: [],
        location: '',
        description: '',
    },
}
const endpoint = '/api/events';


const calendarApi = ref(null)
const refCalendar = ref()
const loading = ref(false);
const isEventHandlerSidebarActive = ref(false);
const editMode = ref(false);
const isLeftSidebarOpen = ref(true);
const events = ref([]);
const eventTypes = ref({});
const selectedEventTypes = ref({});
// const selectedEvent = ref();
const selectedEvent = ref(structuredClone(blankEvent))
const eventDetails =ref()
const startTime = ref();
const value = ref(new Date());
const type = ref('month');
const types = ref(['month', 'week', 'day', 'list']);
const initialTimeZone = 'Europe/Berlin';

const checkAll = computed({

    /*GET: Return boolean `true` => if length of options matches length of selected filters => Length matches when all events are selected
  SET: If value is `true` => then add all available options in selected filters => Select All
  Else if => all filters are selected (by checking length of both array) => Empty Selected array  => Deselect All
  */
    get: () => selectedEventTypes.value.length === eventTypes.value.length,
    set: val => {
        if (val)
            selectedEventTypes.value = eventTypes.value.map(i => i.name)
        else if (selectedEventTypes.value.length === eventTypes.value.length)
            selectedEventTypes.value = []
    },
})

const fetchEvents = async () => {
    try {
        const response = await axios.get('/api/events');
        // console.log('events', response)
        const events = response.data.data.events.map(event => ({
            ...event,
            start: new Date(event.start),
            end: new Date(event.end),
        }));
        // console.log('fetchevents',events)
        return events; // Returns the processed events array.
    } catch (error) {
        console.error("Error fetching events:", error);
        return []; // Return an empty array in case of an error.
    }
};

const fetchEventTypes = async () => {
    // loadEventDetails.value = true;
    // error.value = null; // Reset previous errors

    try {
        const response = await axios.get(`/api/events/types`);
        return response.data.data;

        // selectedEventTypes = respones.map

        console.log('Event types loaded:', response);
    } catch (err) {
        console.error('Failed to load event types:', err);
        // error.val

    } finally {
        //loadEventDetails.value = false; // Ensure loading state is reset
    }
};

const getEvent = async (eventId) => {
    try {
        // loadEventDetails.value = true;
        const response = await axios.get(`/api/events/${eventId}`);
        const event = response.data
        // loadEventDetails.value = false;
        // console.log('fetchevents',events)
        return event; // Returns the processed events array.
    } catch (error) {
        console.error("Error fetching event:", error);
        return []; // Return an empty array in case of an error.
    }
};

// const getEvent = (eventId) => {
//
//     return axios.get(`/api/events/${eventId}`).then((response) => {
//         console.log(response)
//         return response.data
//         // this.eventData = response.data.data;
//         // this.event = response.data.data.event;
//
//         // this.isLoading = false
//     });
// }
//
const createEvent = () => {
    isEventHandlerSidebarActive.value = true
    editMode.value = true
}


const addEvent = async (addevent) => {
    // console.log('addevent', addevent)
    axios.post(`${endpoint}`, addevent).then(() => {
        event.value = null
        // this.page = {title: "", body: ""};
        // this.message = "Page saved ..link"
    }).catch((error) => {
        if (error.response.status === 422) {
            // this.creating.errors = error.response.data
            this.editing.errors = error.response.data
        }
    })
}

const updateEvent = async (event) => {
    // console.log('updateevent', event)
    // return  await axios.get(`/apps/calendar/${event.id}`, {
    //     method: 'PUT',
    //     body: event,
    // })

    axios.patch(`${endpoint}/${event.id}`, event).then(() => {
        // message.value = "Event updated"

        let foundIndex = events.value.findIndex(x => x.id == event.id);
        // let elementPos = events.value.map(function(x) {return x.id; }).indexOf(event.id);
        // let objectFound = array[elementPos];
        // console.log('foundIndex', foundIndex)
        events.value[foundIndex] = event;
    }).catch((error) => {
        console.log('error', error)
        if (error.response.status === 422) {
            // this.editing.errors = error.response.data
        }
    })
}

const removeEvent = async (eventId) => {
    console.log('removedevent', eventId)
    return await await axios.get(`/apps/calendar/${eventId}`, {
        method: 'DELETE',
    })
}

const jumpToDate = currentDate => {
    calendarApi.value?.gotoDate(new Date(currentDate))
}


const handleEventClick = (info) => {
    // console.log('eventklick', info)

    selectedEvent.value = info.event
    // eventDetails.value = await getEvent(info.event.id)
    editMode.value = false

    // console.log('eventklickevent', selectedEvent.value)
    // console.log('title', selectedEvent.value.title)
    // event.value = { ...event.value, start: event.date }
    isEventHandlerSidebarActive.value = true
}

const handleDateClick = (info) => {
    console.log('info', info)
    selectedEvent.value = structuredClone(blankEvent)
    editMode.value = true
    selectedEvent.value.start = info.date;
    // console.log('date', info)
    // selectedEvent.value = info.event
    // event.value = { ...event.value, start: event.date }
    isEventHandlerSidebarActive.value = true
}

const  calendarOptions =  ref({
    plugins: [
        dayGridPlugin,
        timeGridPlugin,
        interactionPlugin,
        listPlugin,
        customViewPlugin
    ],
    buttonText: {
        custom: 'list'
    },
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,custom'
    },
    initialView: 'dayGridMonth',
    initialEvents: [], // alternatively, use the `events` setting to fetch from a feed
    editable: false,
    selectable: true,
    selectMirror: true,
    dayMaxEvents: true,
    weekends: true,
    timeZone: 'Europe/Berlin',
    events: events,
    eventClick: handleEventClick,
    dateClick: handleDateClick,
    eventTimeFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    },
    eventClassNames({ event: calendarEvent }) {
        const colorName = calendarEvent._def.extendedProps.colorName
        // const colorName = 'green'

        return [
            // Background Color
            `bg-light-${colorName} text-${colorName}`,
        ]
    }
    // eventClick: this.handleEventClick,
    // eventsSet: this.handleEvents
    /* you can update a remote database when these fire:
    eventAdd:
    eventChange:
    eventRemove:
    */
});

watch(isEventHandlerSidebarActive, val => {
    if (!val) {
        editMode.value = true
        selectedEvent.value = structuredClone(blankEvent)
    }

})

eventBus.on('openSidebarWithEvent', (event) => {
    selectedEvent.value = event;
    isEventHandlerSidebarActive.value = true;
});

onMounted(async () => {
    loading.value = true;
    events.value = await fetchEvents();
    eventTypes.value = await fetchEventTypes();
    // console.log('events',events.value)
    loading.value = false;
    calendarApi.value = refCalendar.value.getApi()
});

</script>

<template>
    <v-container>
        <VCard>
            <!-- `z-index: 0` Allows overlapping vertical nav on calendar -->
            <v-layout style="z-index: 0;">
<!--                <EventDatePicker v-model="startdate"></EventDatePicker>-->
                <!-- 👉 Navigation drawer -->

                <VNavigationDrawer
                    v-model="isLeftSidebarOpen"
                    width="292"
                    absolute
                    touchless
                    location="start"
                    class="calendar-add-event-drawer"
                    :temporary="$vuetify.display.mdAndDown"
                >
                    <div class="pa-5">
                        <VBtn
                            block
                            prepend-icon="ri-add-line"
                            @click="createEvent"
                        >
                            Add event
                        </VBtn>
                    </div>

                    <VDivider />

<!--                    <VueDatePicker-->
<!--                        locale="de"-->
<!--                        v-model="startTime"-->
<!--                        :time-picker="true"-->
<!--                        :enable-date-picker="false"-->
<!--                        @update:modelValue="updateStartTime"-->
<!--                    />-->

                    <div class="d-flex align-center justify-center pa-1">
                        <VueDatePicker
                            locale="de"
                            v-model="startTime"
                            :enable-time-picker="false"
                            utc
                            inline
                            auto-apply
                            :preview-format="format"
                            @update:modelValue="jumpToDate"
                        />

                    </div>

                    <VDivider />
                    <div class="pa-5">
                        <h5 class="text-h5 mb-4">
                            Event Filters
                        </h5>

                        <div class="d-flex flex-column calendars-checkbox">
                            <VCheckbox
                                v-model="checkAll"
                                label="View all"
                            />
                            <VCheckbox
                                v-for="type in eventTypes"
                                :key="type.name"
                                v-model="selectedEventTypes"
                                :value="type.name"
                                :color="type.color"
                                :label="type.name"
                            />
                        </div>
                    </div>
                </VNavigationDrawer>

                <VMain>
                    <VCard flat>
                        <FullCalendar
                            ref="refCalendar"
                            :options="calendarOptions"
                        />
                    </VCard>
                </VMain>
            </v-layout>
        </VCard>
        <CalendarEventHandler
            v-model:isDrawerOpen="isEventHandlerSidebarActive"
            :event="selectedEvent"
            :eventTypes="eventTypes"
            :editMode="editMode"
            @add-event="addEvent"
            @update-event="updateEvent"
            @remove-event="removeEvent"
        />
    </v-container>
</template>

<style lang='css'>

h2 {
    margin: 0;
    font-size: 16px;
}

ul {
    margin: 0;
    padding: 0 0 0 1.5em;
}

li {
    margin: 1.5em 0;
    padding: 0;
}

b { /* used for event dates/times */
    margin-right: 3px;
}

.demo-app {
    display: flex;
    min-height: 100%;
    font-family: Arial, Helvetica Neue, Helvetica, sans-serif;
    font-size: 14px;
}

.demo-app-sidebar {
    width: 300px;
    line-height: 1.5;
    background: #eaf9ff;
    border-right: 1px solid #d3e2e8;
}

.demo-app-sidebar-section {
    padding: 2em;
}

.demo-app-main {
    flex-grow: 1;
    padding: 3em;
}

.fc { /* the calendar root */
    max-width: 1100px;
    margin: 0 auto;
}

.fc .fc-button .fc-icon {
    vertical-align: bottom;
}
.fc-custom-view {
    overflow-y:scroll;
}

.event-list-custom {
    list-style-type: none;
    margin:0;
    padding:0;

    li {
        margin:0;
        padding: 8px 14px;
    }

    li {
        border: 1px solid #ddd;
        border-top: 0;
    }

    .fc-list-day-text, .fc-list-day-side-text {
        font-weight: bold;
    }

    .fc-list-event-time {
        display: inline-block;
        width: 5em;
    }

    .fc-list-event-dot {
        margin: 0 1em;
    }

}

</style>

<!--<style lang="scss">-->
<!--//@use "@core/scss/template/libs/full-calendar";-->

<!--.calendars-checkbox {-->
<!--    .v-label {-->
<!--        color: rgba(var(&#45;&#45;v-theme-on-surface), var(&#45;&#45;v-high-emphasis-opacity));-->
<!--        opacity: var(&#45;&#45;v-high-emphasis-opacity);-->
<!--    }-->
<!--}-->

<!--.calendar-add-event-drawer {-->
<!--    &.v-navigation-drawer:not(.v-navigation-drawer&#45;&#45;temporary) {-->
<!--        border-end-start-radius: 0.375rem;-->
<!--        border-start-start-radius: 0.375rem;-->
<!--    }-->
<!--}-->

<!--.calendar-date-picker {-->
<!--    display: none;-->

<!--    +.flatpickr-input {-->
<!--        +.flatpickr-calendar.inline {-->
<!--            border: none;-->
<!--            box-shadow: none;-->

<!--            .flatpickr-months {-->
<!--                border-block-end: none;-->
<!--            }-->
<!--        }-->
<!--    }-->

<!--    & ~ .flatpickr-calendar .flatpickr-weekdays {-->
<!--        margin-block: 0 4px;-->
<!--    }-->
<!--}-->
<!--</style>-->


