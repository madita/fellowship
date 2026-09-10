<template>
    <div>
        <page-header
            :title="$t('events.title')"
            :subtitle="$t('events.subtitle')"
            icon="mdi-calendar-month"
            fluid
        >
            <template #actions>
                <v-btn color="primary" variant="elevated" prepend-icon="mdi-plus" @click="createEvent">
                    {{ $t('events.addNewEvent') }}
                </v-btn>
            </template>
            <v-tabs v-model="activeTab" color="primary" class="calendar-tabs">
                <v-tab value="calendar">{{ $t('events.calendarView') }}</v-tab>
                <v-tab value="overview">{{ $t('events.eventsOverview') }}</v-tab>
            </v-tabs>
        </page-header>

        <v-container fluid class="pa-0">
            <v-card class="calendar-container" rounded="0" variant="flat">
                <v-window v-model="activeTab">
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
                                        variant="tonal"
                                        prepend-icon="mdi-plus"
                                        @click="createEvent"
                                    >
                                        {{ $t('events.addNewEvent') }}
                                    </v-btn>
                                </div>

                                <v-divider class="my-2" />

                                <div class="d-flex align-center justify-center py-2">
                                    <VueDatePicker
                                    :locale="userLocale"
                                    v-model="startTime"
                                    :enable-time-picker="false"
                                    :timezone="userTimezone"
                                    inline
                                    auto-apply
                                    :preview-format="userDateFormat"
                                    :format="userDateFormat"
                                    @update:modelValue="jumpToDate"
                                />
                                </div>

                                <v-divider class="my-2" />

                                <div class="pa-4">
                                    <div class="d-flex align-center justify-space-between mb-4">
                                        <h5 class="text-h6 font-weight-bold">{{ $t('events.eventFilters') }}</h5>
                                        <v-btn
                                            variant="text"
                                            density="comfortable"
                                            size="small"
                                            @click="checkAll = !checkAll"
                                        >{{ checkAll ? $t('events.clearAll') : $t('events.selectAll') }}</v-btn>
                                    </div>

                                    <v-fade-transition hide-on-leave>
                                        <div class="d-flex flex-column calendars-checkbox">
                                            <v-checkbox
                                                v-model="checkAll"
                                                :label="$t('events.viewAll')"
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
                                                :label="translateTypeName(type.name)"
                                                hide-details
                                                density="compact"
                                            />
                                        </div>
                                    </v-fade-transition>
                                </div>

                                <v-divider class="my-2" />

                                <!-- Quick Upcoming Events Preview -->
                                <div class="pa-4">
                                    <h5 class="text-h6 font-weight-bold mb-4">{{ $t('events.comingUpSoon') }}</h5>
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
                                    <empty-state
                                        v-else
                                        icon="mdi-calendar-blank"
                                        :title="$t('events.noUpcomingEvents')"
                                        compact
                                    />
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
                                            <v-btn value="dayGridMonth">{{ $t('events.month') }}</v-btn>
                                            <v-btn value="timeGridWeek">{{ $t('events.week') }}</v-btn>
                                            <v-btn value="timeGridDay">{{ $t('events.day') }}</v-btn>
                                            <v-btn value="custom">{{ $t('events.list') }}</v-btn>
                                        </v-btn-toggle>

                                        <v-btn
                                            :icon="isLeftSidebarOpen ? 'mdi-menu-open' : 'mdi-menu'"
                                            variant="text"
                                            class="d-md-none"
                                            @click="isLeftSidebarOpen = !isLeftSidebarOpen"
                                        />
                                    </div>

                                    <full-calendar
                                        ref="refCalendar"
                                        :key="userTimezone"
                                        :options="calendarOptions"
                                        class="calendar-component"
                                    />
                                </v-card>
                            </v-main>
                        </v-layout>
                        <loading-state v-else />
                    </v-window-item>

                    <!-- Overview Tab -->
                    <v-window-item value="overview">
                        <v-container fluid>
                            <v-row>
                                <!-- Upcoming Events Section -->
                                <v-col cols="12" md="6">
                                    <v-card rounded="lg" variant="elevated" class="h-100">
                                        <v-card-title class="d-flex justify-space-between align-center py-4 px-6">
                                            <div>
                                                <h3 class="text-subtitle-1 font-weight-medium">{{ $t('events.upcomingEvents') }}</h3>
                                                <span class="text-caption text-medium-emphasis">{{ $t('events.next7Days') }}</span>
                                            </div>
                                            <v-badge
                                                :content="upcomingEvents.length"
                                                :color="upcomingEvents.length > 0 ? 'primary' : 'secondary'"
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
                                                    <span>{{ $t('events.event') }}</span>
                                                    <span>{{ $t('events.dateAndTime') }}</span>
                                                </v-list-subheader>

                                                <v-list-item
                                                    v-for="event in upcomingEvents"
                                                    :key="event.id"
                                                    :title="event.title"
                                                    :subtitle="formatEventLocation(event)"
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

                                            <empty-state
                                                v-else
                                                icon="mdi-calendar-blank"
                                                :title="$t('events.noUpcomingEventsNext7Days')"
                                                compact
                                            >
                                                <template #actions>
                                                    <v-btn color="primary" variant="tonal" prepend-icon="mdi-plus" @click="createEvent">
                                                        {{ $t('events.addEvent') }}
                                                    </v-btn>
                                                </template>
                                            </empty-state>
                                        </v-card-text>
                                    </v-card>
                                </v-col>

                                <!-- All Events Section -->
                                <v-col cols="12" md="6">
                                    <v-card rounded="lg" variant="elevated" class="h-100">
                                        <v-card-title class="d-flex justify-space-between align-center py-4 px-6">
                                            <div>
                                                <h3 class="text-subtitle-1 font-weight-medium">{{ $t('events.allEvents') }}</h3>
                                                <span class="text-caption text-medium-emphasis">{{ $t('events.byCategory') }}</span>
                                            </div>
                                            <v-badge
                                                :content="filterEvents.length"
                                                :color="filterEvents.length > 0 ? 'primary' : 'secondary'"
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
                                                                    {{ translateTypeName(typeName) }}
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

                                            <empty-state
                                                v-else
                                                icon="mdi-calendar-blank"
                                                :title="$t('events.noEventsFound')"
                                                compact
                                            >
                                                <template #actions>
                                                    <v-btn color="primary" variant="tonal" prepend-icon="mdi-plus" @click="createEvent">
                                                        {{ $t('events.addEvent') }}
                                                    </v-btn>
                                                </template>
                                            </empty-state>
                                        </v-card-text>
                                    </v-card>
                                </v-col>

                                <!-- Event Statistics Card -->
                                <v-col cols="12">
                                    <v-card rounded="lg" variant="elevated">
                                        <v-card-title class="py-4 px-6">
                                            <h3 class="text-subtitle-1 font-weight-medium">{{ $t('events.eventStatistics') }}</h3>
                                        </v-card-title>

                                        <v-divider />

                                        <v-card-text>
                                            <v-row class="my-2">
                                                <v-col v-for="(stat, index) in eventStats" :key="index" cols="12" sm="6" md="3">
                                                    <v-card variant="tonal" :color="stat.color" class="pa-4" rounded="lg">
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
        </v-container>

        <!-- Event Handler Sidebar (kept from original) -->
        <CalendarEventHandler
            v-model:isDrawerOpen="isEventHandlerSidebarActive"
            :event="selectedEvent"
            :editMode="editMode"
            :saving="saving"
            @add-event="addEvent"
            @update-event="updateEvent"
            @remove-event="removeEvent"
        />
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { addDays, isEqual, isAfter, isBefore } from "date-fns";
import { useDateFormat } from '@/plugins/formatDate.js';

const { t, te } = useI18n();

const translateTypeName = (name) => {
    const key = 'events.types.' + name.toLowerCase();
    return te(key) ? t(key) : name;
};
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import luxon3Plugin from '@fullcalendar/luxon3';
import customViewPlugin from './custom-list-view.js';
import CalendarEventHandler from "./CalendarEventHandler.vue";
import PageHeader from '../common/PageHeader.vue';
import EmptyState from '../common/EmptyState.vue';
import LoadingState from '../common/LoadingState.vue';
import { useCalendarStore } from '@/store/calendarStore.js';
import { useUserStore } from '@/store/userStore.js';
import { useSettingsStore } from '@/store/settingStore.js';
import VueDatePicker from "@vuepic/vue-datepicker";
import eventBus from "../common/eventBus.js";
import { blankLocation, formatEventLocationLabel } from '@/utils/eventLocation.js';
import { useDialog } from '@/composables/useDialog.js';

// Store
const calendarStore = useCalendarStore();
const userStore = useUserStore();
const settingsStore = useSettingsStore();

// Composables
const { formatDate: formatDateUtil } = useDateFormat();
const dialog = useDialog();

// Local state
const activeTab = ref('calendar');
const calendarViewType = ref('dayGridMonth');
const calendarApi = ref(null);
const refCalendar = ref();
const loading = ref(false);
// True while an add/update/remove request from the event drawer is running
const saving = ref(false);
const loadEventTypes = ref(true);
const isEventHandlerSidebarActive = ref(false);
const isDialogActive = ref(false);
const editMode = ref(false);
const isLeftSidebarOpen = ref(true);
const startTime = ref(new Date());
const value = ref(new Date());
const endpoint = '/api/events';
const format = ref('dd.MM.yyyy'); // Date format for the date picker preview

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
        location: blankLocation(),
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
        title: t('events.totalEvents'),
        value: filterEvents.value.length,
        icon: 'mdi-calendar-multiple',
        color: 'primary'
    },
    {
        title: t('events.thisWeek'),
        value: upcomingEvents.value.length,
        icon: 'mdi-calendar-clock',
        color: 'success'
    },
    {
        title: t('events.categories'),
        value: Object.keys(groupedEvents.value).length,
        icon: 'mdi-tag-multiple',
        color: 'info'
    },
    {
        title: t('events.allDayEvents'),
        value: filterEvents.value.filter(e => e.allDay).length,
        icon: 'mdi-calendar-today',
        color: 'warning'
    }
]);

// Get user's timezone preference
const userTimezone = computed(() => {
    const timezone = userStore.user?.timezone ||
           settingsStore.appSettings?.default_timezone ||
           'UTC';
    console.log('timezone',timezone)
    return timezone;
});

// Get user's locale preference
const userLocale = computed(() => {
    const lang = userStore.user?.language || settingsStore.appSettings?.default_language || 'en';
    const localeMap = {
        'en': 'en-US',
        'de': 'de-DE',
        'es': 'es-ES',
        'fr': 'fr-FR',
    };
    return localeMap[lang] || 'en-US';
});

// Get user's date format preference for picker
const userDateFormat = computed(() => {
    const phpFormat = userStore.user?.date_format || settingsStore.appSettings?.date_format || 'Y-m-d';
    const formatMap = {
        'Y-m-d': 'yyyy-MM-dd',
        'd/m/Y': 'dd/MM/yyyy',
        'm/d/Y': 'MM/dd/yyyy',
        'd.m.Y': 'dd.MM.yyyy',
    };
    return formatMap[phpFormat] || 'yyyy-MM-dd';
});

// Get user's time format preference
const userTimeFormatString = computed(() => {
    const timeFormat = userStore.user?.time_format ||
                      settingsStore.appSettings?.time_format ||
                      'H:i:s';
    // Remove seconds for cleaner display
    return timeFormat.replace(':s', '').replace(' s', '');
});

// Get user's time format preference for 12h/24h display (for FullCalendar)
const userTimeFormat = computed(() => {
    return userTimeFormatString.value.includes('A') || userTimeFormatString.value.includes('a');
});

// Calendar configuration
const calendarOptions = computed(() => ({
    plugins: [
        dayGridPlugin,
        timeGridPlugin,
        interactionPlugin,
        listPlugin,
        luxon3Plugin,
        customViewPlugin
    ],
    initialView: calendarViewType.value,
    // Fill the bounded .calendar-main container instead of sizing by
    // aspectRatio (which grows the page). expandRows makes month rows fill
    // the height; timegrid views scroll internally.
    height: '100%',
    expandRows: true,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: '',
    },
    buttonText: {
        custom: t('events.list'),
        today: t('events.today'),
        month: t('events.month'),
        week: t('events.week'),
        day: t('events.day'),
    },
    initialEvents: [],
    editable: false,
    selectable: true,
    selectMirror: true,
    dayMaxEvents: true,
    weekends: true,
    // Use user's preferred timezone with Luxon plugin support
    timeZone: userTimezone.value,
    events: filterEvents.value.map(event => {
        // Events are stored in UTC in database, FullCalendar will convert to user's timezone
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
        hour12: userTimeFormat.value
    },
    // Custom event render to display times in user's timezone
    eventDidMount: function(info) {
        // Events are automatically displayed in the user's timezone by FullCalendar
        // No additional conversion needed
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

// Runs one drawer request: the drawer shows a loader via `saving`, closes on
// success and stays open (with an error dialog) on failure.
const runDrawerRequest = async (request, successKey, errorKey) => {
    if (saving.value) return;
    saving.value = true;
    try {
        await request();
        await calendarStore.fetchEvents();
        isEventHandlerSidebarActive.value = false;
        saving.value = false;
        await dialog.success(t(successKey));
    } catch (error) {
        await dialog.requestError(error, t(errorKey));
    } finally {
        saving.value = false;
    }
};

const addEvent = (addevent) =>
    runDrawerRequest(() => axios.post(`${endpoint}`, addevent), 'events.eventCreated', 'events.saveError');

const updateEvent = (event) =>
    runDrawerRequest(() => axios.patch(`${endpoint}/${event.id}`, event), 'events.eventUpdated', 'events.saveError');

const removeEvent = (eventId) =>
    runDrawerRequest(() => axios.delete(`${endpoint}/${eventId}`), 'events.eventDeleted', 'events.deleteError');

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
    return formatDateUtil(eventDate);
};

const formatEventTime = (event) => {
    if (event.allDay) return t('events.allDay');

    const start = new Date(event.start);
    const end = event.end ? new Date(event.end) : null;

    // Use user's time format preference
    const timeFormat = userTimeFormatString.value;

    if (end) {
        return `${formatDateUtil(start, timeFormat)} - ${formatDateUtil(end, timeFormat)}`;
    }

    return formatDateUtil(start, timeFormat);
};

// The API returns the location as a structured object (or a legacy plain
// string) — reduce it to a display label for the list subtitle.
const formatEventLocation = (event) => formatEventLocationLabel(event, t);

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

// Locale change handler - refetch events to get translated titles/descriptions
async function onLocaleChange() {
    try {
        await calendarStore.fetchEvents();
    } catch (error) {
        console.error('Error refetching events after locale change:', error);
    }
}

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

    // Listen for locale changes to refetch content in new language
    window.addEventListener('locale-changed', onLocaleChange);
});

onUnmounted(() => {
    // Clean up locale change listener
    window.removeEventListener('locale-changed', onLocaleChange);
});
</script>

<style lang="scss">
.calendar-container {
    overflow: hidden;
}

.calendar-sidebar {
    background-color: rgb(var(--v-theme-surface));
    border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.calendar-datepicker {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: none !important;
    width: 100%;
}

.calendar-main {
    background-color: rgb(var(--v-theme-background));
    // Cap the calendar to the viewport so it scrolls internally instead of
    // growing the page. The offset accounts for the app bar, the page header
    // (with tabs) and the view-toggle row above; min-height keeps it usable
    // on short screens.
    display: flex;
    flex-direction: column;
    height: calc(100dvh - 300px);
    min-height: 500px;

    // The view-toggle row is fixed height; the calendar fills the rest.
    .calendar-component {
        flex: 1 1 auto;
        min-height: 0;
    }

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

        // Grid lines follow the theme border colour in light and dark mode
        .fc-scrollgrid {
            border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
        }

        .fc-col-header-cell {
            background-color: rgb(var(--v-theme-surface));
            border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
        }

        .fc-daygrid-day {
            background-color: rgb(var(--v-theme-background));
            border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
        }

        .fc-timegrid-slot {
            border-color: rgba(var(--v-theme-on-surface), 0.08) !important;
        }

        .fc-button {
            background-color: rgb(var(--v-theme-primary)) !important;
            border-color: rgb(var(--v-theme-primary)) !important;

            &:not(:disabled):hover {
                background-color: rgba(var(--v-theme-primary), 0.8) !important;
            }

            &.fc-button-active {
                background-color: rgba(var(--v-theme-primary), 0.9) !important;
            }
        }

        .fc-daygrid-day-number,
        .fc-col-header-cell-cushion,
        .fc-timegrid-slot-label {
            color: rgb(var(--v-theme-on-surface));
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
        border-left: 3px solid rgb(var(--v-theme-#{$color}));
    }
}
</style>
