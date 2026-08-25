<script setup>
import { ref, watch, computed, nextTick, onMounted  } from 'vue';
import { useI18n } from 'vue-i18n';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import CustomDatePicker from "../common/CustomDatePicker.vue";

const { t, te } = useI18n();

const translateTypeName = (name) => {
    const key = 'events.types.' + name.toLowerCase();
    return te(key) ? t(key) : name;
};
import UserAvatar from "../common/UserAvatar.vue";
import axios from "axios";
import { useCalendarStore } from '@/store/calendarStore.js';
import ConfirmDialog from '../common/ConfirmDialog.vue';
import ProfileDialog from '../common/ProfileDialog.vue';
import DetailsDialog from '../common/DetailsDialog.vue';
import RelatedContent from '../common/RelatedContent.vue';
import { useUserStore } from "@/store/userStore.js";
import { useSettingsStore } from "@/store/settingStore.js";
import { useDateFormat } from '@/plugins/formatDate.js';
import { buildViewLocation, mergeTopLevelIntoExtendedProps } from '@/utils/eventLocation.js';

const props = defineProps({
    isDrawerOpen: Boolean,
    editMode: Boolean,
    event: Object,
});

const emit = defineEmits([
    'update:isDrawerOpen',
    'addEvent',
    'updateEvent',
    'removeEvent',
]);

const selectedStatus = ref(null);
const showConfirmationDialog = ref(false);
const showProfileDialog = ref(false);
const showRelateContentDialog = ref(false);
const showDetailsDialog = ref(false);
const guestResponses = ref({});

const calendarStore = useCalendarStore();
const userStore = useUserStore();
const settingsStore = useSettingsStore();
const { formatDate: formatDateUtil } = useDateFormat();
const localEditMode = ref(props.editMode);

// Get user's time format preference (without seconds for display)
const userTimeFormat = computed(() => {
    const timeFormat = userStore.user?.time_format ||
                      settingsStore.appSettings?.time_format ||
                      'H:i:s';
    // Remove seconds for cleaner display
    return timeFormat.replace(':s', '').replace(' s', '');
});
const refForm = ref();
const isFocused = ref(true);
const loadEventDetails = ref(true);

const localEventTypes = computed(() => calendarStore.eventTypes);
const eventAnswers = ref([]);
const eventGuests = ref([]);
const isGoing = ref(null);
const isStartDateValid = ref(true);
const isEndDateValid = ref(true);
const profileAnswer = ref(null);

const confirmationDialog = ref(null);
const relatedItems = ref([]);

const openConfirmationDialog = () => {
    confirmationDialog.value.isOpen = true;
};

const localEvent = ref(null);
const initialSnapshot = ref('');
watch(
    () => props.event,
    (newEvent) => {
        // Raw API items (list/upcoming views) carry location & co. at top
        // level; only FullCalendar transposes them into extendedProps.
        // Merging gives the form and view mode a single shape — and stops
        // saving a list-opened event from wiping its stored location.
        localEvent.value = newEvent
            ? mergeTopLevelIntoExtendedProps(JSON.parse(JSON.stringify(newEvent)))
            : null;
        if (localEvent.value?.id) {
            getEvent(localEvent.value.id);
            fetchRelatedItems('App\\Models\\Event\\Event', localEvent.value.id);
        }
    },
    { immediate: true }
);

// Which location modes this event type allows, from options.location
// (["custom","real","virtual"]). Falls back to custom-only.
const selectedEventTypeId = computed(() =>
    localEvent.value?.extendedProps?.event_type_id ?? localEvent.value?.event_type_id ?? null
);
const allowedLocationModes = computed(() => {
    const type = Object.values(localEventTypes.value).find(t => t.id === selectedEventTypeId.value);
    let opts = type?.options;
    if (typeof opts === 'string') {
        try { opts = JSON.parse(opts); } catch (e) { opts = {}; }
    }
    const modes = opts?.location;
    return Array.isArray(modes) && modes.length ? modes : ['custom'];
});

// Keep the selected mode valid for the chosen event type.
watch([allowedLocationModes, selectedEventTypeId], () => {
    const loc = localEvent.value?.extendedProps?.location;
    if (!loc) return;
    if (!loc.type || !allowedLocationModes.value.includes(loc.type)) {
        loc.type = allowedLocationModes.value[0] ?? null;
    }
});

const locationModeIcon = (mode) => ({
    real: 'mdi-map-marker',
    virtual: 'mdi-web',
    custom: 'mdi-map-marker-outline',
}[mode] || 'mdi-map-marker');

// IRC channels for the "virtual" picker, loaded lazily.
const ircChannels = ref([]);
const ircChannelsLoading = ref(false);
const fetchIrcChannels = async () => {
    if (ircChannels.value.length || ircChannelsLoading.value) return;
    ircChannelsLoading.value = true;
    try {
        const { data } = await axios.get('/api/irc/available-channels');
        ircChannels.value = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error('Failed to load IRC channels:', e);
    } finally {
        ircChannelsLoading.value = false;
    }
};

// How the location renders in view mode: an icon, a label and (optionally) a
// link — a Google Maps search for physical addresses, the internal IRC client
// for a channel, or the raw URL for an online link.
const viewLocation = computed(() => buildViewLocation(localEvent.value?.extendedProps?.location, t));

const isDirty = computed(() => {
    if (!initialSnapshot.value) return false;
    return JSON.stringify(localEvent.value) !== initialSnapshot.value;
});

const eventTypeItems = computed(() => Object.values(localEventTypes.value));

const eventType = computed(() => {
    return Object.values(localEventTypes.value).find(item => item.name === localEvent.value?.extendedProps?.type);
});

const user = computed(() => {
    return userStore.user || { id: null };
});

const resetEvent = () => {
    isStartDateValid.value = true;
    isEndDateValid.value = true;
    nextTick(() => refForm.value?.resetValidation());
};

const canJoinEvent = computed(() => {

    if (!localEvent.value?.id) return false;
    // const now = new Date();
    const utcDate = new Date();
    utcDate.setTime(utcDate.getTime() + utcDate.getTimezoneOffset() * 60000);
    console.log('enddate', localEvent.value.end, utcDate )
    // return localEvent.value.end ? new Date(localEvent.value.end) > now : new Date(localEvent.value.start) > now;
    return localEvent.value.end ? new Date(localEvent.value.end) >= utcDate : new Date(localEvent.value.start) >= utcDate;
});

const removeEvent = () => {
    emit('removeEvent', String(localEvent.value.id));
    emit('update:isDrawerOpen', false);
};

const openDialog = () => {
    showDetailsDialog.value = true;
};

const handleSubmit = () => {
    validateStartDate();
    validateEndDate();

    refForm.value?.validate().then(({ valid }) => {
        if (valid) {
            localEditMode.value = false;

            if ('id' in localEvent.value)
                emit('updateEvent', localEvent.value);
            else
                emit('addEvent', localEvent.value);

            emit('update:isDrawerOpen', false);
        }
    });
};

const eventTypeOptions = computed(() => {
    //the fullcalender uses extendedProps but for all other the values should be accessed directly
    if(localEvent.value?.extendedProps?.event_type_id) {
        localEvent.value.event_type_id = localEvent.value?.extendedProps?.event_type_id
    }
    const type = Object.values(localEventTypes.value).find(item => item.id === localEvent.value?.event_type_id);
    return type === undefined ? [] : JSON.parse(JSON.stringify(type.options));
});

const eventProfile = computed(() => eventTypeOptions.value.profile?.fields || []);

const onCancel = () => {
    emit('update:isDrawerOpen', false);
};

const getEvent = async (eventId) => {
    try {
        loadEventDetails.value = true;
        const response = await axios.get(`/api/events/${eventId}`);
        eventAnswers.value = response.data.answers;
        eventGuests.value = response.data.guests;
        isGoing.value = response.data.isGoing;
    } catch (err) {
        console.error('Failed to load event details:', err);
    } finally {
        loadEventDetails.value = false;
    }
};

const fetchRelatedItems = async (model, eventId) => {
    try {
        const response = await axios.post('/api/related-items', {
            modelType: model, modelId: eventId,
        });
        relatedItems.value = response.data.items;
    } catch (error) {
        console.error('Failed to fetch related items:', error);
    }
};

const approveGuest = async (guestId, action) => {
    try {
        await axios.post(`/api/events/${localEvent.value.id}/approve-guest`, {
            guestId,
            action,
        });

        // Update the guest's approved status in all answer types
        const updatedAnswers = {};

        // Process each answer type (going, notgoing, etc.)
        Object.keys(eventAnswers.value).forEach(answerType => {
            // Create a new array for this answer type
            updatedAnswers[answerType] = eventAnswers.value[answerType].map(guest => {
                // If this is the guest we're approving/rejecting, update their pivot data
                if (guest.pivot && guest.pivot.user_id === guestId) {
                    return {
                        ...guest,
                        pivot: {
                            ...guest.pivot,
                            approved_at: action === "approve" ? new Date().toISOString() : null
                        }
                    };
                }
                return guest;
            });
        });

        // Replace the entire eventAnswers object
        eventAnswers.value = updatedAnswers;

        // Refresh the event data to ensure we have the latest state
        getEvent(localEvent.value.id);
    } catch (error) {
        console.error(`Failed to ${action} guest:`, error);
    }
};

const filterGuestsByApproval = (answers) => {
    const guestsRequiringApproval = {};
    const approvedGuests = {};

    Object.keys(answers).forEach((status) => {
        guestsRequiringApproval[status] = [];
        approvedGuests[status] = [];
        answers[status].forEach((guest) => {
            if (
                eventTypeOptions.value.guest &&
                eventTypeOptions.value.guest.includes("approval") &&
                !guest.pivot.approved_at
            ) {
                guestsRequiringApproval[status].push(guest);
            } else {
                approvedGuests[status].push(guest);
            }
        });
    });

    return { guestsRequiringApproval, approvedGuests };
};

const validateStartDate = () => {
    isStartDateValid.value = !!localEvent.value.start;
};

const validateEndDate = () => {
    isEndDateValid.value = !!localEvent.value.end;
};
const joinEvent = (answer) => {
    const type = eventType.value;

    // Don't do anything if selecting the same option that's already selected, but do if profile can be changed...
    if (isGoing.value && isGoing.value.type === answer && !type?.options?.profile?.includes(answer)) {
        return;
    }

    // Store previous state for potential rollback
    const previousGoing = isGoing.value ? { ...isGoing.value } : null;
    const previousAnswer = previousGoing?.type;

    // Optimistically update the UI for immediate feedback
    isGoing.value = {
        ...(isGoing.value || {}),
        type: answer,
        profile: isGoing.value?.profile || {}
    };

    // FIXED: Create a complete copy of the eventAnswers object to work with
    const updatedAnswers = {};

    // Copy all current answer lists, but filtering out the current user
    Object.keys(eventAnswers.value).forEach(answerType => {
        if (Array.isArray(eventAnswers.value[answerType])) {
            // Create a new array without the current user
            updatedAnswers[answerType] = eventAnswers.value[answerType].filter(guest =>
                guest.id !== user.value.id && guest.pivot.user_id !== user.value.id
            );
        } else {
            // If not an array, just copy whatever it is
            updatedAnswers[answerType] = eventAnswers.value[answerType];
        }
    });

    // Ensure the target answer list exists
    if (!updatedAnswers[answer]) {
        updatedAnswers[answer] = [];
    }

    // Check if user is already in this list (shouldn't be after filtering, but check anyway)
    const userExists = updatedAnswers[answer].some(guest =>
        guest.id === user.value.id || guest.pivot?.user_id === user.value.id
    );

    if (!userExists) {
        // Create a copy of the user with pivot data
        const userWithPivot = {
            ...user.value,
            pivot: {
                user_id: user.value.id,
                ...((!type?.options?.profile?.includes(answer)) ? { approved_at: new Date().toISOString() } : {})
            }
        };

        updatedAnswers[answer].push(userWithPivot);
    }

    // FIXED: Replace entire eventAnswers object with our modified copy
    eventAnswers.value = updatedAnswers;

    if (type?.options?.profile?.includes(answer)) {
        profileAnswer.value = answer;
        showProfileDialog.value = true;
    } else {
        if (!localEvent.value?.id) {
            console.error('Event ID is missing');
            return;
        }

        axios.post(`/api/events/${localEvent.value.id}/answer`, {answer})
            .then(response => {
                // Server confirmed the update
                if (response.data && response.data.going) {
                    isGoing.value = response.data.going;
                }

                // Replace entire answers object with server response
                if (response.data && response.data.answers) {
                    eventAnswers.value = JSON.parse(JSON.stringify(response.data.answers));
                }
            })
            .catch((error) => {
                console.error('Error updating event answer:', error);

                // Revert optimistic update on error
                isGoing.value = previousGoing;

                // FIXED: Create a new object for error recovery
                const recoveryAnswers = {};

                // Copy all current lists except for the attempted answer
                Object.keys(eventAnswers.value).forEach(answerType => {
                    if (answerType !== answer) {
                        recoveryAnswers[answerType] = [...eventAnswers.value[answerType]];
                    }
                });

                // Ensure previous answer type exists if we had one
                if (previousAnswer && !recoveryAnswers[previousAnswer]) {
                    recoveryAnswers[previousAnswer] = [];
                }

                // Add user back to previous answer type if there was one
                if (previousAnswer) {
                    // Only add back if not already there
                    const alreadyExists = recoveryAnswers[previousAnswer].some(guest =>
                        guest.id === user.value.id || guest.pivot?.user_id === user.value.id
                    );

                    if (!alreadyExists) {
                        const userWithPivot = {
                            ...user.value,
                            pivot: {
                                user_id: user.value.id,
                                approved_at: previousGoing?.pivot?.approved_at || new Date().toISOString()
                            }
                        };
                        recoveryAnswers[previousAnswer].push(userWithPivot);
                    }
                }

                // Make sure answer type exists
                if (!recoveryAnswers[answer]) {
                    recoveryAnswers[answer] = [];
                }

                // Replace entire object
                eventAnswers.value = recoveryAnswers;

                if (error.response?.status === 422) console.error('Validation failed:', error.response.data);
            });
    }
};

const dialogModelValueUpdate = (val) => {
    // Block scrim/Esc close when there are unsaved changes; the close/cancel
    // buttons go through onCancel and bypass this guard.
    if (!val && isDirty.value) return;
    emit('update:isDrawerOpen', val);
};

const rules = {
    title: [v => !!v || t('events.titleRequired')],
    date: [v => !!v || t('events.dateRequired')]
};

const handleConfirmation = (isConfirmed) => {
    if (isConfirmed) {
        removeEvent(localEvent.value.id);
    }
};

const handleProfile = (isConfirmed) => {
    // Perform the profile save action
    if (!isConfirmed) {
        //console.log('AAAcancelprofile')
    }
};

const handleRelationConfirmed = (relation) => {
    // Handle the relation
};

const formatDateRange = computed(() => {
    if (!localEvent.value) return '';

    const startDate = new Date(localEvent.value.start);
    const endDate = localEvent.value.end ? new Date(localEvent.value.end) : null;

    // Format the date part using user's date format preference
    let formattedDate = formatDateUtil(startDate);

    // Add time part using user's time format preference (without seconds for display)
    formattedDate += ' ' + formatDateUtil(startDate, userTimeFormat.value);

    if (endDate) {
        // If same day, just show end time
        if (startDate.toDateString() === endDate.toDateString()) {
            formattedDate += ` - ${formatDateUtil(endDate, userTimeFormat.value)}`;
        } else {
            // Different day, show full date and time
            formattedDate += ` - ${formatDateUtil(endDate)} ${formatDateUtil(endDate, userTimeFormat.value)}`;
        }
    }

    return formattedDate;
});

// Utility function to round a date to the next hour or 15-minute increment
const roundDateToNextTimeIncrement = (date) => {
    const now = new Date(date);
    const minutes = now.getMinutes();
    const seconds = now.getSeconds();
    const milliseconds = now.getMilliseconds();

    // Find the next 15-minute increment
    const minutesToAdd = 15 - (minutes % 15);

    // If already exactly on a 15-minute mark and no seconds/ms, don't increment
    if (minutesToAdd === 15 && seconds === 0 && milliseconds === 0) {
        return now;
    }

    // Round to next 15-minute increment
    now.setMinutes(minutes + minutesToAdd, 0, 0); // Reset seconds and milliseconds

    return now;
};

// Handle start date change
// Handle start date change
const handleStartDateChange = (newStartDate) => {
    // First, ensure the start date is properly rounded
    if (newStartDate && !localEvent.value.allDay) {
        // Convert to Date object if it's not already
        const startDate = new Date(newStartDate);

        // Round to next 15-minute increment
        const roundedStartDate = roundDateToNextTimeIncrement(startDate);

        // Only update if different to avoid loops
        if (roundedStartDate.getTime() !== startDate.getTime()) {
            localEvent.value.start = roundedStartDate;
            newStartDate = roundedStartDate;
        }
    }

    if (!localEvent.value.end || new Date(localEvent.value.end) < new Date(newStartDate)) {
        // For all-day events, set the same date
        if (localEvent.value.allDay) {
            localEvent.value.end = newStartDate;
        } else {
            // For events with time, set end date 1 hour after start date
            const endDate = new Date(newStartDate);

            // If the start time was adjusted to the next hour, make sure the end time is still 1 hour later
            endDate.setHours(endDate.getHours() + 1);

            // Ensure minutes are aligned to 15-minute intervals
            const minutes = endDate.getMinutes();
            const roundedMinutes = Math.ceil(minutes / 15) * 15;
            endDate.setMinutes(roundedMinutes);

            localEvent.value.end = endDate;
        }
    }
}

// Watch for changes in the all-day toggle
watch(() => localEvent.value.allDay, (isAllDay) => {
    if (isAllDay && localEvent.value.start && localEvent.value.end) {
        // If switching to all-day...
    } else if (!localEvent.value.allDay && localEvent.value.start) {
        // If switching from all-day to timed, set reasonable defaults
        // Update start date to next rounded time
        localEvent.value.start = roundDateToNextTimeIncrement(new Date());

        // Set end date 1 hour after start
        const endDate = new Date(localEvent.value.start);
        endDate.setHours(endDate.getHours() + 1);
        localEvent.value.end = endDate;
    }
});

watch(() => props.editMode, () => {
    localEditMode.value = props.editMode;
});

watch(() => props.isDrawerOpen, (isOpen) => {
    resetEvent();
    if (isOpen) {
        // Re-sync from the prop on every open. The watcher on props.editMode
        // alone is not enough: localEditMode is mutated locally (submit, the
        // view/edit toggle), so the prop can still hold the value the parent
        // wants while the local copy has drifted — and an unchanged prop
        // fires no watcher.
        localEditMode.value = props.editMode;

        // Snapshot after localEvent has been set from props, so it reflects
        // the unedited starting state.
        nextTick(() => {
            initialSnapshot.value = JSON.stringify(localEvent.value);
        });
    } else {
        initialSnapshot.value = '';
    }
});

onMounted(() => {
    fetchIrcChannels();
    if (!localEvent.value.start) {
        // Set initial start date with proper timezone handling
        localEvent.value.start = roundDateToNextTimeIncrement(new Date());
    } else {
        // If we already have a date (from editing an event), ensure it's displayed correctly
        // The date picker handles timezone conversion using user preferences from userStore/settingsStore
    }
});

</script>

<template>
    <VNavigationDrawer
        temporary
        :persistent="isDirty"
        location="end"
        :model-value="props.isDrawerOpen"
        width="420"
        class="event-drawer"
        @update:model-value="dialogModelValueUpdate"
    >
        <!-- Header Section -->
        <div class="event-drawer-header" :class="{ 'edit-mode': localEditMode }">
            <div v-if="localEditMode" class="d-flex align-center py-3 px-4">
                <h5 class="text-h5 font-weight-medium">{{ localEvent?.id ? $t('events.updateEvent') : $t('events.addEvent') }}</h5>
                <VSpacer/>
                <VBtn
                    v-if="localEvent?.id"
                    color="primary"
                    variant="text"
                    class="me-2"
                    @click="localEditMode = !localEditMode"
                >
                    {{ localEditMode ? $t('events.view') : $t('events.edit') }}
                </VBtn>
            </div>

            <div v-else class="d-flex align-center py-3 px-4">
                <div>
                    <h5 class="text-h5 font-weight-medium mb-1">{{ localEvent?.title }}</h5>
                    <div class="text-subtitle-2 text-medium-emphasis">
                        <v-icon size="small" class="me-1">mdi-calendar</v-icon>
                        {{ formatDateRange }}
                    </div>
                </div>

                <VSpacer/>

                <slot name="beforeClose"/>

                <div class="action-buttons">
                    <!-- Removed the details icon from header -->

                    <v-btn
                        icon="mdi-pencil"
                        variant="text"
                        color="primary"
                        density="comfortable"
                        class="action-btn"
                        @click="localEditMode = true"
                        :title="$t('events.edit')"
                    />

                    <v-btn
                        icon="mdi-link-variant"
                        variant="text"
                        color="primary"
                        density="comfortable"
                        class="action-btn"
                        @click="showRelateContentDialog = true"
                        :title="$t('events.relatedContent')"
                    />

                    <v-btn
                        icon="mdi-delete"
                        variant="text"
                        color="error"
                        density="comfortable"
                        class="action-btn"
                        @click="showConfirmationDialog = true"
                        :title="$t('events.delete')"
                    />

                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        density="comfortable"
                        class="action-btn"
                        @click="dialogModelValueUpdate(false)"
                        :title="$t('common.close')"
                    />
                </div>
            </div>
        </div>

        <VDivider/>

        <PerfectScrollbar :options="{ wheelPropagation: false }" class="event-drawer-content">
            <!-- Edit Mode Form -->
            <VCard flat class="px-2" v-if="localEditMode">
                <VCardText>
                    <!-- Unsaved changes warning -->
                    <VAlert
                        v-if="isDirty"
                        type="info"
                        variant="tonal"
                        density="compact"
                        icon="mdi-information-outline"
                        class="mb-4"
                        :text="$t('events.unsavedChangesWarning')"
                    />

                    <VForm ref="refForm" @submit.prevent="handleSubmit">
                        <VRow>
                            <!-- Event Type Select -->
                            <VCol cols="12" v-if="eventTypeItems.length > 0">
                                <VSelect
                                    v-model="localEvent.extendedProps.event_type_id"
                                    :label="$t('events.type')"
                                    :placeholder="$t('events.selectEventType')"
                                    :items="eventTypeItems"
                                    :item-title="item => translateTypeName(item.name)"
                                    :item-value="item => item.id"
                                    variant="outlined"
                                    density="comfortable"
                                >
                                    <template #selection="{ item }">
                                        <div class="d-flex align-center">
                                            <VIcon
                                                icon="mdi-circle-medium"
                                                :color="item.raw.color"
                                                class="me-2"
                                            />
                                            <span>{{ translateTypeName(item.raw.name) }}</span>
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

                            <template v-if="localEvent?.extendedProps?.event_type_id">
                                <VCol cols="12">
                                    <VTextField
                                        v-model="localEvent.title"
                                        :label="$t('common.title')"
                                        :rules="rules.title"
                                        variant="outlined"
                                        density="comfortable"
                                        prepend-inner-icon="mdi-format-title"
                                    />
                                </VCol>

                                <VCol cols="12">
                                    <CustomDatePicker
                                        :label="$t('events.startDate')"
                                        v-model="localEvent.start"
                                        :error="!isStartDateValid"
                                        :error-messages="[$t('events.startDateRequired')]"
                                        @date-selected="handleStartDateChange"
                                        :id="'start-date'"
                                        :all-day="localEvent.allDay"
                                    />
                                </VCol>

                                <VCol cols="12" v-show="eventTypeOptions.showAttributtes?.includes('endDate')">
                                    <CustomDatePicker
                                        :label="$t('events.endDate')"
                                        v-model="localEvent.end"
                                        :error="!isEndDateValid"
                                        :error-messages="[$t('events.endDateRequired')]"
                                        :min-date="localEvent.start"
                                        :is-end-date="true"
                                        :id="'end-date'"
                                        :all-day="localEvent.allDay"
                                    />
                                </VCol>

                                <VCol cols="12" v-show="eventTypeOptions.showAttributtes?.includes('allDay')">
                                    <VSwitch
                                        color="primary"
                                        v-model="localEvent.allDay"
                                        :label="$t('events.allDay')"
                                    />
                                </VCol>

                                <VCol cols="12" v-if="localEvent.extendedProps.location">
                                    <!-- Mode selector: only when the event type allows more than one -->
                                    <VBtnToggle
                                        v-if="allowedLocationModes.length > 1"
                                        v-model="localEvent.extendedProps.location.type"
                                        color="primary"
                                        density="comfortable"
                                        mandatory
                                        class="mb-3 flex-wrap"
                                    >
                                        <VBtn
                                            v-for="mode in allowedLocationModes"
                                            :key="mode"
                                            :value="mode"
                                            :prepend-icon="locationModeIcon(mode)"
                                        >
                                            {{ $t('events.locationModes.' + mode) }}
                                        </VBtn>
                                    </VBtnToggle>

                                    <!-- real: physical address (rendered as a map link in view mode) -->
                                    <VTextField
                                        v-if="localEvent.extendedProps.location.type === 'real'"
                                        v-model="localEvent.extendedProps.location.address"
                                        :label="$t('events.locationAddress')"
                                        variant="outlined"
                                        density="comfortable"
                                        prepend-inner-icon="mdi-map-marker"
                                    />

                                    <!-- virtual: an internal IRC channel or an external URL -->
                                    <template v-else-if="localEvent.extendedProps.location.type === 'virtual'">
                                        <VBtnToggle
                                            v-model="localEvent.extendedProps.location.virtualMode"
                                            color="primary"
                                            density="comfortable"
                                            mandatory
                                            class="mb-3"
                                        >
                                            <VBtn value="irc" prepend-icon="mdi-pound">{{ $t('events.locationIrc') }}</VBtn>
                                            <VBtn value="url" prepend-icon="mdi-link-variant">{{ $t('events.locationUrl') }}</VBtn>
                                        </VBtnToggle>

                                        <VSelect
                                            v-if="localEvent.extendedProps.location.virtualMode === 'irc'"
                                            v-model="localEvent.extendedProps.location.irc_channel_id"
                                            :items="ircChannels"
                                            item-title="name"
                                            item-value="id"
                                            :label="$t('events.locationIrcChannel')"
                                            :loading="ircChannelsLoading"
                                            :no-data-text="$t('events.locationNoChannels')"
                                            variant="outlined"
                                            density="comfortable"
                                            prepend-inner-icon="mdi-pound"
                                            clearable
                                        >
                                            <template #item="{ props: itemProps, item }">
                                                <VListItem
                                                    v-bind="itemProps"
                                                    :title="item.raw.name"
                                                    :subtitle="item.raw.server"
                                                />
                                            </template>
                                        </VSelect>
                                        <VTextField
                                            v-else
                                            v-model="localEvent.extendedProps.location.url"
                                            :label="$t('events.locationUrl')"
                                            placeholder="https://"
                                            variant="outlined"
                                            density="comfortable"
                                            prepend-inner-icon="mdi-link-variant"
                                        />
                                    </template>

                                    <!-- custom: free text -->
                                    <VTextField
                                        v-else
                                        v-model="localEvent.extendedProps.location.text"
                                        :label="$t('events.location')"
                                        variant="outlined"
                                        density="comfortable"
                                        prepend-inner-icon="mdi-map-marker-outline"
                                    />
                                </VCol>

                                <VCol cols="12">
                                    <VTextarea
                                        v-model="localEvent.extendedProps.description"
                                        :label="$t('common.description')"
                                        variant="outlined"
                                        density="comfortable"
                                        rows="4"
                                        counter
                                        prepend-inner-icon="mdi-text-box-outline"
                                    />
                                </VCol>
                            </template>

                            <VCol cols="12" class="d-flex justify-end">
                                <VBtn
                                    type="submit"
                                    color="primary"
                                    class="me-3"
                                >
                                    {{ $t('events.submit') }}
                                </VBtn>
                                <VBtn
                                    variant="outlined"
                                    color="secondary"
                                    @click="onCancel"
                                >
                                    {{ $t('common.cancel') }}
                                </VBtn>
                            </VCol>
                        </VRow>
                    </VForm>
                </VCardText>
            </VCard>

            <!-- View Mode Content -->
            <div v-else class="event-view-content">
                <!-- Event Info Section -->
                <v-card flat class="event-info-card mb-4">
                    <v-card-text>
                        <!-- Location Info -->
                        <div class="event-info-item mb-4">
                            <div class="info-label">
                                <v-icon color="primary" class="mr-2">mdi-map-marker</v-icon>
                                <span>{{ $t('events.location') }}</span>
                            </div>
                            <div class="info-content">
                                <template v-if="viewLocation">
                                    <a
                                        v-if="viewLocation.external"
                                        :href="viewLocation.href"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="d-inline-flex align-center location-link"
                                    >
                                        <v-icon size="18" class="mr-1">{{ viewLocation.icon }}</v-icon>{{ viewLocation.label }}
                                    </a>
                                    <router-link
                                        v-else-if="viewLocation.to"
                                        :to="viewLocation.to"
                                        class="d-inline-flex align-center location-link"
                                    >
                                        <v-icon size="18" class="mr-1">{{ viewLocation.icon }}</v-icon>{{ viewLocation.label }}
                                    </router-link>
                                    <span v-else class="d-inline-flex align-center">
                                        <v-icon size="18" class="mr-1">{{ viewLocation.icon }}</v-icon>{{ viewLocation.label }}
                                    </span>
                                </template>
                                <template v-else>{{ $t('events.noLocationSpecified') }}</template>
                            </div>
                        </div>

                        <!-- Description Info -->
                        <div class="event-info-item" v-if="localEvent?.extendedProps?.description">
                            <div class="info-label">
                                <v-icon color="primary" class="mr-2">mdi-text-box-outline</v-icon>
                                <span>{{ $t('common.description') }}</span>
                            </div>
                            <div class="info-content description-content"
                                 v-html="localEvent.extendedProps.description"></div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Response Section -->
                <v-card
                    v-if="canJoinEvent"
                    flat
                    class="mb-4 response-card"
                    rounded="lg"
                    elevation="0"
                >
                    <v-card-text>
                        <h3 class="text-h6 mb-3">{{ $t('events.areYouComing') }}</h3>
                        <div class="d-flex flex-wrap gap-2">
                            <VBtn
                                v-for="(answer, value) in eventTypeOptions.answers"
                                :key="`answer-${value}`"
                                :color="['going', 'participant'].includes(answer.key) ? 'success' : answer.key === 'notgoing' ? 'error' : 'primary'"
                                :variant="isGoing && isGoing.type === value ? 'elevated' : 'outlined'"
                                class="response-btn mr-1"
                                @click="joinEvent(answer.key)"
                            >
                                <v-icon
                                    v-if="isGoing && isGoing.type === value"
                                    size="small"
                                    start
                                    class="me-1"
                                >
                                    mdi-check-circle
                                </v-icon>
                                {{ te('events.rsvp.' + answer.key) ? t('events.rsvp.' + answer.key) : answer.value }}
                            </VBtn>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Attendees Section -->
                <v-card flat class="attendees-card mb-4" v-if="Object.keys(eventAnswers).length > 0">
                    <v-card-text>
                        <div class="d-flex align-center justify-space-between mb-3">
                            <h3 class="text-h6">{{ $t('events.attendees') }}</h3>
                            <v-btn
                                icon="mdi-information-outline"
                                variant="text"
                                color="primary"
                                density="comfortable"
                                size="small"
                                @click="openDialog()"
                                :title="$t('events.viewDetailedAttendeeList')"
                            />
                        </div>

                        <div v-for="(guests, status) in filterGuestsByApproval(eventAnswers).approvedGuests"
                             :key="`status-${status}`"
                             class="mb-4">
                            <div class="d-flex align-center mb-2">
                                <v-chip
                                    :color="['going', 'participant'].includes(status) ? 'success' : status === 'notgoing' ? 'error' : 'primary'"
                                    size="small"
                                    class="me-2"
                                >
                                    {{ te('events.rsvp.' + status) ? t('events.rsvp.' + status) : status }}
                                </v-chip>
                                <span class="text-subtitle-2">{{ $t('events.peopleCount', { count: guests.length }) }}</span>
                            </div>

                            <div class="d-flex flex-wrap gap-1">
                                <UserAvatar
                                    v-for="guest in guests"
                                    :key="guest.id"
                                    :user="guest"
                                    class="mr-1 mb-1"
                                />
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Pending Approvals Section TODO only show if creator(done) or admin-->
                <v-card
                    flat
                    class="approval-card mb-4"
                    v-if="localEvent?.extendedProps?.user_id === user.id &&
                         eventTypeOptions.guest &&
                         eventTypeOptions.guest.includes('approval')"
                >
                    <v-card-text>
                        <h3 class="text-h6 mb-3">{{ $t('events.pendingApprovals') }}</h3>

                        <div v-for="(guests, status) in filterGuestsByApproval(eventAnswers).guestsRequiringApproval"
                             :key="`pending-${status}`"
                             class="mb-4">
                            <v-list-subheader>{{ te('events.rsvp.' + status) ? t('events.rsvp.' + status) : status }}</v-list-subheader>

                            <v-list>
                                <v-list-item
                                    v-for="guest in guests"
                                    :key="guest.id"
                                    class="pending-guest-item"
                                >
                                    <template #prepend>
                                        <UserAvatar :user="guest"/>
                                    </template>

                                    <v-list-item-title>{{ guest.name }}</v-list-item-title>

                                    <template #append>
                                        <div class="d-flex">
                                            <v-btn
                                                size="small"
                                                color="success"
                                                variant="text"
                                                icon="mdi-check"
                                                class="me-1"
                                                @click="approveGuest(guest.pivot.user_id, 'approve')"
                                            ></v-btn>
                                            <v-btn
                                                size="small"
                                                color="error"
                                                variant="text"
                                                icon="mdi-close"
                                                @click="approveGuest(guest.pivot.user_id, 'reject')"
                                            ></v-btn>
                                        </div>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Related Content Section -->
                <v-card flat class="related-content-card" v-if="relatedItems.length > 0">
                    <v-card-text>
                        <h3 class="text-h6 mb-3">{{ $t('events.relatedContent') }}</h3>

                        <div class="related-items-grid">
                            <v-card
                                v-for="item in relatedItems"
                                :key="item.id"
                                class="related-item-card"
                                elevation="2"
                                rounded="lg"
                                :to="`/gallery/${item.related.slug}`"
                            >
                                <v-img
                                    v-if="item.related.coverImage"
                                    :src="item.related.coverImage"
                                    height="140"
                                    cover
                                    class="related-item-image"
                                ></v-img>
                                <v-img
                                    v-else
                                    src="https://via.placeholder.com/300x140"
                                    height="140"
                                    cover
                                    class="related-item-image"
                                ></v-img>

                                <v-card-text class="pa-3">
                                    <h4 class="text-subtitle-1 font-weight-medium text-truncate mb-1">
                                        {{ item.related.title }}
                                    </h4>
                                    <p class="text-caption text-medium-emphasis text-truncate">
                                        {{ item.related.description || $t('events.relatedContent') }}
                                    </p>
                                </v-card-text>
                            </v-card>
                        </div>
                    </v-card-text>
                </v-card>
            </div>
        </PerfectScrollbar>
    </VNavigationDrawer>

    <!-- Dialogs -->
    <ProfileDialog
        v-if="localEvent.id > 0"
        v-model="showProfileDialog"
        :event="localEvent"
        :is-going="isGoing"
        :answer="profileAnswer"
        :resolve="handleProfile"
    />

    <ConfirmDialog
        v-model="showConfirmationDialog"
        :title="$t('events.deleteEvent')"
        :content="$t('events.confirmDeleteEvent')"
        :confirmationText="$t('common.delete')"
        :cancellationText="$t('common.cancel')"
        :resolve="handleConfirmation"
    />

    <RelatedContent
        v-model="showRelateContentDialog"
        :contentName="$t('events.currentEvent')"
        initialSourceType="App\Models\Event\Event"
        :initialSourceItem="String(localEvent?.id)"
        @confirmRelation="handleRelationConfirmed"
    />

    <DetailsDialog
        v-if="localEvent.id > 0"
        v-model="showDetailsDialog"
        :eventGuests="eventGuests"
        :event="localEvent"
    />
</template>

<style scoped>
.event-drawer {
    max-height: 100%;
    border-left: 1px solid rgba(0, 0, 0, 0.12);
}

.event-drawer-header {
    /*background-color: rgb(var(--v-theme-surface));*/
    position: sticky;
    top: 0;
    z-index: 10;
}

.event-drawer-header.edit-mode {
    /*background-color: rgb(var(--v-theme-surface-variant));*/
}

.event-drawer-content {
    height: calc(100vh - 65px);
}

.action-buttons {
    display: flex;
    align-items: center;
}

.action-btn {
    margin-left: 4px;
}

.event-view-content {
    padding: 16px;
}

.event-info-card,
.attendees-card,
.approval-card,
.related-content-card {
    /*border: 1px solid rgba(var(--v-theme-on-surface), 0.08);*/
    border-radius: 12px;
    overflow: hidden;
}

.response-card {
    /*border: 1px solid rgba(var(--v-theme-primary), 0.15);*/
    border-radius: 12px;
    overflow: hidden;
    /*background-color: rgba(var(--v-theme-primary), 0.03);*/
}

.event-info-item {
    margin-bottom: 12px;
}

.info-label {
    display: flex;
    align-items: center;
    font-weight: 500;
    /*color: rgb(var(--v-theme-primary));*/
    margin-bottom: 4px;
}

.info-content {
    padding-left: 28px;
    /*color: rgb(var(--v-theme-on-surface));*/
}

.description-content {
    white-space: pre-line;
}

.response-btn {
    flex-grow: 1;
    max-width: 120px;
    font-weight: 500;
    letter-spacing: 0.5px;
}

.related-items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
}

.related-item-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.related-item-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
}

.related-item-image {
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

.pending-guest-item {
    border-radius: 8px;
    margin-bottom: 4px;
}

.pending-guest-item:hover {
    /*background-color: rgba(var(--v-theme-on-surface), 0.04);*/
}
</style>
