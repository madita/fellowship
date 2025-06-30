<template>
    <v-container fluid class="pa-0">
        <v-row>
            <v-col cols="12">
                <v-card class="calendar-container elevation-3">
                    <v-tabs
                        v-model="activeTab"
                        bg-color="primary"
                        centered
                        dark
                        class="calendar-tabs"
                    >
                        <v-tab value="calendar">Calendar View</v-tab>
                        <v-tab value="overview">Events Overview</v-tab>
                    </v-tabs>

                    <v-window v-model="activeTab" class="mt-2">
                        <!-- Calendar Tab -->
                        <v-window-item value="calendar">
                            <!-- Calendar View -->
                            <v-layout style="z-index: 0;" v-if="!loading">
                                <!-- Left Sidebar -->
                                <v-navigation-drawer
                                    v-model="isLeftSidebarOpen"
                                    width="300"
                                    absolute
                                    touchless
                                    location="start"
                                    class="calendar-sidebar rounded-lg"
                                    :temporary="$vuetify.display.mdAndDown"
                                    elevation="3"
                                >
                                    <div class="pa-4">
                                        <v-btn
                                            block
                                            color="primary"
                                            prepend-icon="ri-add-line"
                                            @click="createEvent"
                                            class="text-none font-weight-bold"
                                            size="large"
                                            rounded="lg"
                                        >
                                            Add New Event
                                        </v-btn>
                                    </div>

                                    <v-divider class="my-2" />

                                    <div class="d-flex align-center justify-center py-2">
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

                                    <v-divider class="my-2" />

                                    <div class="pa-4">
                                        <div class="d-flex align-center justify-space-between mb-4">
                                            <h5 class="text-h6 font-weight-bold">Event Filters</h5>
                                            <v-btn
                                                variant="text"
                                                density="comfortable"
                                                size="small"
                                                @click="checkAll = !checkAll"
                                            >{{ checkAll ? 'Clear All' : 'Select All' }}</v-btn>
                                        </div>

                                        <v-fade-transition hide-on-leave>
                                            <div class="d-flex flex-column calendars-checkbox">
                                                <v-checkbox
                                                    v-model="checkAll"
                                                    label="View all"
                                                    color="primary"
                                                    hide-details
                                                    density="compact"
                                                />
                                                <v-checkbox
                                                    v-for="type in calendarStore.eventTypes"
                                                    :key="type.name"
                                                    v-model="calendarStore.selectedEventTypes"
                                                    :value="type.name"
                                                    :color="type.color"
                                                    :label="type.name"
                                                    hide-details
                                                    density="compact"
                                                />
                                            </div>
                                        </v-fade-transition>
                                    </div>

                                    <v-divider class="my-2" />

                                    <!-- Quick Upcoming Events Preview -->
                                    <div class="pa-4">
                                        <h5 class="text-h6 font-weight-bold mb-4">Coming Up Soon</h5>
                                        <div v-if="upcomingEvents.length > 0">
                                            <v-list lines="two" class="pa-0">
                                                <v-list-item
                                                    v-for="event in upcomingEvents.slice(0, 3)"
                                                    :key="event.id"
                                                    rounded="lg"
                                                    class="mb-2"
                                                    :title="event.title"
                                                    :subtitle="formatEventTime(event)"
                                                    :prepend-icon="getEventIcon(event.type)"
                                                    :class="`event-item-${getEventClass(event.type)}`"
                                                    @click="viewEventDetails(event)"
                                                >
                                                    <template v-slot:append>
                                                        <v-chip
                                                            size="small"
                                                            :color="getEventColor(event.type)"
                                                            class="text-white"
                                                            variant="flat"
                                                        >
                                                            {{ event.type }}
                                                        </v-chip>
                                                    </template>
                                                </v-list-item>
                                            </v-list>
                                        </div>
                                        <div v-else class="text-center pa-4 text-body-2 text-disabled">
                                            No upcoming events
                                        </div>
                                    </div>
                                </v-navigation-drawer>

                                <!-- Main Calendar Content -->
                                <v-main>
                                    <v-card
                                        flat
                                        class="pa-4 calendar-main"
                                        rounded="lg"
                                    >
                                        <div class="d-flex justify-space-between align-center mb-4">
                                            <v-btn-toggle
                                                v-model="calendarViewType"
                                                color="primary"
                                                rounded="lg"
                                                mandatory
                                                density="comfortable"
                                            >
                                                <v-btn value="dayGridMonth">Month</v-btn>
                                                <v-btn value="timeGridWeek">Week</v-btn>
                                                <v-btn value="timeGridDay">Day</v-btn>
                                                <v-btn value="custom">List</v-btn>
                                            </v-btn-toggle>

                                            <v-btn
                                                icon
                                                variant="text"
                                                @click="isLeftSidebarOpen = !isLeftSidebarOpen"
                                                class="d-md-none"
                                            >
                                                <v-icon>{{ isLeftSidebarOpen ? 'mdi-menu-open' : 'mdi-menu' }}</v-icon>
                                            </v-btn>
                                        </div>

                                        <full-calendar
                                            ref="refCalendar"
                                            :options="calendarOptions"
                                            class="calendar-component"
                                        />
                                    </v-card>
                                </v-main>
                            </v-layout>
                            <v-sheet v-else class="d-flex justify-center align-center" height="500">
                                <v-progress-circular indeterminate color="primary" size="64" />
                            </v-sheet>
                        </v-window-item>

                        <!-- Overview Tab -->
                        <v-window-item value="overview">
                            <v-container>
                                <v-row>
                                    <!-- Upcoming Events Section -->
                                    <v-col cols="12" md="6">
                                        <v-card class="rounded-lg elevation-2 h-100">
                                            <v-card-title class="d-flex justify-space-between align-center py-4 px-6">
                                                <div>
                                                    <h3 class="text-h5 font-weight-bold">Upcoming Events</h3>
                                                    <span class="text-caption text-medium-emphasis">Next 7 days</span>
                                                </div>
                                                <v-badge
                                                    :content="upcomingEvents.length"
                                                    :color="upcomingEvents.length > 0 ? 'primary' : 'grey'"
                                                    offset-x="5"
                                                    offset-y="5"
                                                >
                                                    <v-icon size="large" color="primary">mdi-calendar-clock</v-icon>
                                                </v-badge>
                                            </v-card-title>

                                            <v-divider />

                                            <v-card-text class="pa-0">
                                                <v-list v-if="upcomingEvents.length > 0" class="py-0">
                                                    <v-list-subheader class="d-flex justify-space-between px-6">
                                                        <span>Event</span>
                                                        <span>Date & Time</span>
                                                    </v-list-subheader>

                                                    <v-list-item
                                                        v-for="event in upcomingEvents"
                                                        :key="event.id"
                                                        :title="event.title"
                                                        :subtitle="event.extendedProps?.location || 'No location'"
                                                        class="px-6 event-list-item"
                                                        @click="viewEventDetails(event)"
                                                    >
                                                        <template v-slot:prepend>
                                                            <v-avatar
                                                                :color="getEventColor(event.type)"
                                                                size="36"
                                                                class="text-white mr-3"
                                                            >
                                                                <v-icon>{{ getEventIcon(event.type) }}</v-icon>
                                                            </v-avatar>
                                                        </template>

                                                        <template v-slot:append>
                                                            <div class="text-right">
                                                                <div class="text-body-2">{{ formatEventDate(event) }}</div>
                                                                <div class="text-caption text-medium-emphasis">{{ formatEventTime(event) }}</div>
                                                            </div>
                                                        </template>
                                                    </v-list-item>
                                                </v-list>

                                                <v-sheet v-else class="d-flex flex-column justify-center align-center py-12">
                                                    <v-icon size="64" color="grey-lighten-2">mdi-calendar-blank</v-icon>
                                                    <span class="text-medium-emphasis mt-4">No upcoming events for the next 7 days</span>
                                                    <v-btn variant="text" color="primary" class="mt-4" @click="createEvent">
                                                        Add Event
                                                    </v-btn>
                                                </v-sheet>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>

                                    <!-- All Events Section -->
                                    <v-col cols="12" md="6">
                                        <v-card class="rounded-lg elevation-2 h-100">
                                            <v-card-title class="d-flex justify-space-between align-center py-4 px-6">
                                                <div>
                                                    <h3 class="text-h5 font-weight-bold">All Events</h3>
                                                    <span class="text-caption text-medium-emphasis">By category</span>
                                                </div>
                                                <v-badge
                                                    :content="filterEvents.length"
                                                    :color="filterEvents.length > 0 ? 'primary' : 'grey'"
                                                    offset-x="5"
                                                    offset-y="5"
                                                >
                                                    <v-icon size="large" color="primary">mdi-calendar-month</v-icon>
                                                </v-badge>
                                            </v-card-title>

                                            <v-divider />

                                            <v-card-text class="pa-0">
                                                <div v-if="filterEvents.length > 0">
                                                    <v-expansion-panels variant="accordion" class="event-panels">
                                                        <v-expansion-panel
                                                            v-for="(typeGroup, typeName) in groupedEvents"
                                                            :key="typeName"
                                                        >
                                                            <v-expansion-panel-title>
                                                                <v-row no-gutters>
                                                                    <v-col cols="2">
                                                                        <v-avatar :color="getEventColor(typeName)" size="36" class="text-white">
                                                                            <v-icon>{{ getEventIcon(typeName) }}</v-icon>
                                                                        </v-avatar>
                                                                    </v-col>
                                                                    <v-col cols="8" class="d-flex align-center">
                                                                        {{ typeName }}
                                                                    </v-col>
                                                                    <v-col cols="2" class="text-right">
                                                                        <v-chip
                                                                            size="small"
                                                                            :color="getEventColor(typeName)"
                                                                            variant="elevated"
                                                                            class="text-white"
                                                                        >
                                                                            {{ typeGroup.length }}
                                                                        </v-chip>
                                                                    </v-col>
                                                                </v-row>
                                                            </v-expansion-panel-title>
                                                            <v-expansion-panel-text>
                                                                <v-list lines="two" class="pa-0">
                                                                    <v-list-item
                                                                        v-for="event in typeGroup"
                                                                        :key="event.id"
                                                                        :title="event.title"
                                                                        :subtitle="formatEventDate(event)"
                                                                        @click="viewEventDetails(event)"
                                                                        class="event-list-item"
                                                                    >
                                                                        <template v-slot:append>
                                                                            <v-btn
                                                                                icon
                                                                                variant="text"
                                                                                size="small"
                                                                                color="primary"
                                                                                @click.stop="jumpToEventDate(event.start)"
                                                                            >
                                                                                <v-icon>mdi-calendar-arrow-right</v-icon>
                                                                            </v-btn>
                                                                        </template>
                                                                    </v-list-item>
                                                                </v-list>
                                                            </v-expansion-panel-text>
                                                        </v-expansion-panel>
                                                    </v-expansion-panels>
                                                </div>

                                                <v-sheet v-else class="d-flex flex-column justify-center align-center py-12">
                                                    <v-icon size="64" color="grey-lighten-2">mdi-calendar-blank</v-icon>
                                                    <span class="text-medium-emphasis mt-4">No events found</span>
                                                    <v-btn variant="text" color="primary" class="mt-4" @click="createEvent">
                                                        Add Event
                                                    </v-btn>
                                                </v-sheet>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>

                                    <!-- Event Statistics Card -->
                                    <v-col cols="12">
                                        <v-card class="rounded-lg elevation-2">
                                            <v-card-title class="py-4 px-6">
                                                <h3 class="text-h5 font-weight-bold">Event Statistics</h3>
                                            </v-card-title>

                                            <v-divider />

                                            <v-card-text>
                                                <v-row class="my-2">
                                                    <v-col v-for="(stat, index) in eventStats" :key="index" cols="12" sm="6" md="3">
                                                        <v-card flat class="pa-4 rounded-lg" :color="stat.color + '-lighten-5'">
                                                            <div class="d-flex align-center">
                                                                <v-avatar :color="stat.color" size="48" class="text-white mr-4">
                                                                    <v-icon size="large">{{ stat.icon }}</v-icon>
                                                                </v-avatar>
                                                                <div>
                                                                    <div class="text-h4 font-weight-bold">{{ stat.value }}</div>
                                                                    <div class="text-caption text-medium-emphasis">{{ stat.title }}</div>
                                                                </div>
                                                            </div>
                                                        </v-card>
                                                    </v-col>
                                                </v-row>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                </v-row>
                            </v-container>
                        </v-window-item>
                    </v-window>
                </v-card>
            </v-col>
        </v-row>

        <!-- Event Handler Sidebar (kept from original) -->
        <CalendarEventHandler
            v-model:isDrawerOpen="isEventHandlerSidebarActive"
            :event="selectedEvent"
            :editMode="editMode"
            @add-event="addEvent"
            @update-event="updateEvent"
            @remove-event="removeEvent"
        />
    </v-container>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import axios from 'axios';
import { format, addDays, isEqual, isAfter, isBefore, formatDistance } from "date-fns";
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import customViewPlugin from './custom-list-view.js';
import CalendarEventHandler from "./CalendarEventHandler.vue";
import { useCalendarStore } from '@/store/calendarStore.js';
import VueDatePicker from "@vuepic/vue-datepicker";
import eventBus from "../common/eventBus.js";

// Store
const calendarStore = useCalendarStore();

// Local state
const activeTab = ref('calendar');
const calendarViewType = ref('dayGridMonth');
const calendarApi = ref(null);
const refCalendar = ref();
const loading = ref(false);
const loadEventTypes = ref(true);
const isEventHandlerSidebarActive = ref(false);
const isDialogActive = ref(false);
const editMode = ref(false);
const isLeftSidebarOpen = ref(true);
const startTime = ref(new Date());
const value = ref(new Date());
const endpoint = '/api/events';

// Blank event template
const blankEvent = {
    title: '',
    start: '',
    end: '',
    allDay: false,
    url: '',
    extendedProps: {
        calendar: undefined,
        guests: [],
        location: '',
        description: '',
        event_profile_id: 0,
    },
};
const selectedEvent = ref(structuredClone(blankEvent));

// Check all computed property
const checkAll = computed({
    get: () => calendarStore.selectedEventTypes.length === Object.values(calendarStore.eventTypes).length,
    set: val => {
        if (val)
            calendarStore.selectedEventTypes = Object.values(calendarStore.eventTypes).map(i => i.name);
        else if (calendarStore.selectedEventTypes.length === Object.values(calendarStore.eventTypes).length)
            calendarStore.selectedEventTypes = [];
    },
});

// Filtered events based on selected types
const filterEvents = computed(() => {
    if (calendarStore.selectedEventTypes.length === Object.values(calendarStore.eventTypes).length) {
        return calendarStore.events;
    }
    return calendarStore.events.filter((event) => {
        return calendarStore.selectedEventTypes.indexOf(event.type) !== -1;
    });
});

// Upcoming events (next 7 days)
const upcomingEvents = computed(() => {
    const now = new Date();
    const nextWeek = addDays(now, 7); //
    const nextYear = addDays(now, 365); //

    return filterEvents.value
        .filter(event => {
            const eventStart = new Date(event.start);
            const eventEnd = new Date(event.end);
            // console.log('eventEnd', eventEnd, 'now', now, isAfter(eventEnd, now))
            // if(equal(evenEnd, now)) return true;

            return isAfter(eventEnd, now) && isBefore(eventStart, nextYear);
        })
        .sort((a, b) => new Date(a.start) - new Date(b.start));
});

// Group events by type
const groupedEvents = computed(() => {
    const grouped = {};

    filterEvents.value.forEach(event => {
        if (!grouped[event.type]) {
            grouped[event.type] = [];
        }
        grouped[event.type].push(event);
    });

    // Sort events within each group by date
    Object.keys(grouped).forEach(type => {
        grouped[type].sort((a, b) => new Date(a.start) - new Date(b.start));
    });

    return grouped;
});

// Event statistics
const eventStats = computed(() => [
    {
        title: 'Total Events',
        value: filterEvents.value.length,
        icon: 'mdi-calendar-multiple',
        color: 'primary'
    },
    {
        title: 'This Week',
        value: upcomingEvents.value.length,
        icon: 'mdi-calendar-clock',
        color: 'success'
    },
    {
        title: 'Categories',
        value: Object.keys(groupedEvents.value).length,
        icon: 'mdi-tag-multiple',
        color: 'info'
    },
    {
        title: 'All-day Events',
        value: filterEvents.value.filter(e => e.allDay).length,
        icon: 'mdi-calendar-today',
        color: 'warning'
    }
]);

// Calendar configuration
const calendarOptions = computed(() => ({
    plugins: [
        dayGridPlugin,
        timeGridPlugin,
        interactionPlugin,
        listPlugin,
        customViewPlugin
    ],
    initialView: calendarViewType.value,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: '',
    },
    buttonText: {
        custom: 'List',
        today: 'Today',
        month: 'Month',
        week: 'Week',
        day: 'Day',
    },
    initialEvents: [],
    editable: false,
    selectable: true,
    selectMirror: true,
    dayMaxEvents: true,
    weekends: true,
    // Use UTC timezone for FullCalendar to properly handle the dates
    timeZone: 'local',
    events: filterEvents.value.map(event => {
        // Ensure event dates are properly formatted for FullCalendar
        const mappedEvent = { ...event };
        if (mappedEvent.start) {
            mappedEvent.start = new Date(mappedEvent.start).toISOString();
        }
        if (mappedEvent.end) {
            mappedEvent.end = new Date(mappedEvent.end).toISOString();
        }
        return mappedEvent;
    }),
    eventClick: handleEventClick,
    dateClick: handleDateClick,
    eventTimeFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    },
    // Custom event render to display times in local timezone
    eventDidMount: function(info) {
        // You can customize how events are displayed here
        // For example, add custom tooltips with local times
        if (!info.event.allDay) {
            const startLocal = new Date(info.event.start);
            const tooltip = format(startLocal, 'HH:mm');

            // You could add a tooltip or modify the event title/time display
            // This is optional and depends on your UI requirements
        }
    },
    eventClassNames({ event: calendarEvent }) {
        const colorName = calendarEvent._def.extendedProps.colorName || 'primary';
        return [
            `bg-light-${colorName} text-${colorName}`,
        ];
    }
}));

// Methods
const createEvent = () => {
    selectedEvent.value = structuredClone(blankEvent);
    editMode.value = true;
    isEventHandlerSidebarActive.value = true;
    activeTab.value = 'calendar';
};

const addEvent = async (addevent) => {
    console.log('addEvent');
    try {
        await axios.post(`${endpoint}`, addevent);
        await calendarStore.fetchEvents();
    } catch (error) {
        console.error('Error adding event:', error);
    }
};

const updateEvent = async (event) => {
    console.log('update');
    try {
        await axios.patch(`${endpoint}/${event.id}`, event);
        await calendarStore.fetchEvents();
    } catch (error) {
        console.error('Error updating event:', error);
    }
};

const removeEvent = async (eventId) => {
    try {
        await axios.delete(`${endpoint}/${eventId}`);
        await calendarStore.fetchEvents();
    } catch (error) {
        console.error('Error removing event:', error);
    }
};

const jumpToDate = (currentDate) => {
    const calendarApi = refCalendar.value.getApi();
    calendarApi.gotoDate(new Date(currentDate));
    activeTab.value = 'calendar';
};

const jumpToEventDate = (eventDate) => {
    jumpToDate(eventDate);
    calendarViewType.value = 'timeGridDay';
};

const handleEventClick = (info) => {
    selectedEvent.value = info.event;
    editMode.value = false;
    isEventHandlerSidebarActive.value = true;
};

const handleDateClick = (info) => {
    selectedEvent.value = structuredClone(blankEvent);
    editMode.value = true;
    selectedEvent.value.start = new Date(info.date);
    isEventHandlerSidebarActive.value = true;
};

const viewEventDetails = (event) => {
    console.log('viewEventDetails', event)
    selectedEvent.value = event;
    editMode.value = false;
    isEventHandlerSidebarActive.value = true;
};

// Helper methods for event display
const formatEventDate = (event) => {
    const eventDate = new Date(event.start);
    return format(eventDate, 'EEE, MMM d, yyyy');
};

const formatEventTime = (event) => {
    if (event.allDay) return 'All day';

    const start = new Date(event.start);
    const end = event.end ? new Date(event.end) : null;

    if (end) {
        return `${format(start, 'HH:mm')} - ${format(end, 'HH:mm')}`;
    }

    return format(start, 'HH:mm');
};

// const getEventColor = (type) => {
//     console.log('getEventColor', type)
//     console.log('calendarStore', calendarStore.eventTypes)
//     const eventType = calendarStore.eventTypes.find(t => t.name === type);
//     return eventType?.color || 'primary';
// };

const getEventColor = (type) => {
    // Check if eventTypes is an object or array
    if (!calendarStore.eventTypes) return 'primary';

    // If it's an object with numeric keys (from keyBy)
    if (typeof calendarStore.eventTypes === 'object' && !Array.isArray(calendarStore.eventTypes)) {
        // Convert to array and then find
        const typesArray = Object.values(calendarStore.eventTypes);
        const eventType = typesArray.find(t => t.name === type);
        return eventType?.color || 'primary';
    }

    // If it's already an array
    const eventType = calendarStore.eventTypes.find?.(t => t.name === type);
    return eventType?.color || 'primary';
};

const getEventClass = (type) => {
    return type ? type.toLowerCase().replace(/\s+/g, '-') : 'default';
};

const getEventIcon = (type) => {
    // Map event types to icons
    const iconMap = {
        'Meeting': 'mdi-account-group',
        'Conference': 'mdi-microphone',
        'Workshop': 'mdi-hammer-wrench',
        'Holiday': 'mdi-beach',
        'Personal': 'mdi-account',
        'Work': 'mdi-briefcase'
    };

    return iconMap[type] || 'mdi-calendar-text';
};

// Watchers
watch(isEventHandlerSidebarActive, val => {
    if (!val) {
        editMode.value = true;
        selectedEvent.value = structuredClone(blankEvent);
    }
});

watch(isDialogActive, val => {
    isDialogActive.value = val;
});

watch(calendarViewType, val => {
    if (refCalendar.value) {
        const calendarApi = refCalendar.value.getApi();
        calendarApi.changeView(val);
    }
});

// Event bus listeners
eventBus.on('openSidebarWithEvent', (event) => {
    selectedEvent.value = event;
    editMode.value = false;
    isEventHandlerSidebarActive.value = true;
});

// Lifecycle hooks
onMounted(async () => {
    loading.value = true;

    try {
        await calendarStore.fetchEvents();
        await calendarStore.fetchEventTypes();

        if (refCalendar.value) {
            calendarApi.value = refCalendar.value.getApi();
        }
    } catch (error) {
        console.error('Error loading calendar data:', error);
    } finally {
        loading.value = false;
    }
});
</script>

<style lang="scss">
.calendar-container {
    overflow: hidden;
    border-radius: 12px;
}

.calendar-sidebar {
    background-color: #fafafa;
    border-right: 1px solid rgba(0, 0, 0, 0.08);
}

.calendar-datepicker {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: none !important;
    width: 100%;
}

.calendar-main {
    background-color: #fff;
    min-height: 700px;

    .fc {
        height: 100%;

        .fc-header-toolbar {
            flex-wrap: wrap;
            padding: 0.5rem;
        }

        .fc-button {
            text-transform: capitalize;
            font-weight: 500;
        }

        .fc-event {
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: 500;
            transition: transform 0.2s;

            &:hover {
                transform: translateY(-2px);
            }
        }

        .fc-day-today {
            background-color: rgba(var(--v-theme-primary), 0.05) !important;
        }
    }
}

.event-list-item {
    cursor: pointer;
    transition: background-color 0.2s;

    &:hover {
        background-color: rgba(var(--v-theme-primary), 0.05);
    }
}

.event-panels {
    .v-expansion-panel-title {
        min-height: 56px;
    }
}

// Event type specific styling
@each $type, $color in (
    meeting: 'primary',
    conference: 'secondary',
    workshop: 'success',
    holiday: 'warning',
    personal: 'info',
    work: 'error'
) {
    .event-item-#{$type} {
        border-left: 3px solid var(--v-theme-#{$color});
    }
}
</style>
