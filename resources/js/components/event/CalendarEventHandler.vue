<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import CustomDatePicker from "../common/CustomDatePicker.vue";
import UserAvatar from "../common/UserAvatar.vue";
import axios from "axios";
import { useCalendarStore } from '@/store/calendarStore.js';
import ConfirmDialog from '../common/ConfirmDialog.vue';
import ProfileDialog from '../common/ProfileDialog.vue';
import DetailsDialog from '../common/DetailsDialog.vue';
import RelatedContent from '../common/RelatedContent.vue';
import { useUserStore } from "@/store/userStore.js";

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
const localEditMode = ref(props.editMode);
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
const userStore = useUserStore();

const openConfirmationDialog = () => {
    confirmationDialog.value.isOpen = true;
};

const localEvent = ref(null);
watch(
    () => props.event,
    (newEvent) => {
        localEvent.value = newEvent ? JSON.parse(JSON.stringify(newEvent)) : null;
        if (localEvent.value?.id) {
            getEvent(localEvent.value.id);
            fetchRelatedItems('App\\Models\\Event\\Event', localEvent.value.id);
        }
    },
    { immediate: true }
);

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
    const now = new Date();
    return localEvent.value.end ? new Date(localEvent.value.end) > now : new Date(localEvent.value.start) > now;
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

        eventAnswers.value = eventAnswers.value.map((group) =>
            group.map((guest) =>
                guest.id === guestId ? { ...guest, approved: action === "approve" } : guest
            )
        );
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

    // Don't do anything if selecting the same option that's already selected
    if (isGoing.value && isGoing.value.type === answer) {
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
    emit('update:isDrawerOpen', val);
};

const rules = {
    title: [v => !!v || 'Title is required'],
    date: [v => !!v || 'Date is required'],
    location: [v => !!v || 'Location is required'],
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

    const options = {weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'};
    let formattedDate = startDate.toLocaleDateString(undefined, options);

    if (endDate) {
        // If same day, just show time
        if (startDate.toDateString() === endDate.toDateString()) {
            formattedDate += ` - ${endDate.toLocaleTimeString(undefined, {hour: '2-digit', minute: '2-digit'})}`;
        } else {
            formattedDate += ` - ${endDate.toLocaleDateString(undefined, options)}`;
        }
    }

    return formattedDate;
});

watch(() => props.editMode, () => {
    localEditMode.value = props.editMode;
});

watch(() => props.isDrawerOpen, resetEvent);
</script>

<template>
    <VNavigationDrawer
        temporary
        location="end"
        :model-value="props.isDrawerOpen"
        width="420"
        class="event-drawer"
        @update:model-value="dialogModelValueUpdate"
    >
        <!-- Header Section -->
        <div class="event-drawer-header" :class="{ 'edit-mode': localEditMode }">
            <div v-if="localEditMode" class="d-flex align-center py-3 px-4">
                <h5 class="text-h5 font-weight-medium">{{ localEvent?.id ? 'Update Event' : 'Add Event' }}</h5>
                <VSpacer/>
                <VBtn
                    v-if="localEvent?.id"
                    color="primary"
                    variant="text"
                    class="me-2"
                    @click="localEditMode = !localEditMode"
                >
                    {{ localEditMode ? 'View' : 'Edit' }}
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
                        title="Edit event"
                    />

                    <v-btn
                        icon="mdi-link-variant"
                        variant="text"
                        color="primary"
                        density="comfortable"
                        class="action-btn"
                        @click="showRelateContentDialog = true"
                        title="Related content"
                    />

                    <v-btn
                        icon="mdi-delete"
                        variant="text"
                        color="error"
                        density="comfortable"
                        class="action-btn"
                        @click="showConfirmationDialog = true"
                        title="Delete event"
                    />

                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        density="comfortable"
                        class="action-btn"
                        @click="dialogModelValueUpdate(false)"
                        title="Close"
                    />
                </div>
            </div>
        </div>

        <VDivider/>

        <PerfectScrollbar :options="{ wheelPropagation: false }" class="event-drawer-content">
            <!-- Edit Mode Form -->
            <VCard flat class="px-2" v-if="localEditMode">
                <VCardText>
                    <VForm ref="refForm" @submit.prevent="handleSubmit">
                        <VRow>
                            <!-- Event Type Select -->
                            <VCol cols="12" v-if="eventTypeItems.length > 0">
                                <VSelect
                                    v-model="localEvent.extendedProps.event_type_id"
                                    label="Type"
                                    placeholder="Select Event Type"
                                    :items="eventTypeItems"
                                    :item-title="item => item.name"
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

                            <template v-if="localEvent?.extendedProps?.event_type_id">
                                <VCol cols="12">
                                    <VTextField
                                        v-model="localEvent.title"
                                        label="Title"
                                        :rules="rules.title"
                                        variant="outlined"
                                        density="comfortable"
                                        prepend-inner-icon="mdi-format-title"
                                    />
                                </VCol>

                                <VCol cols="12">
                                    <CustomDatePicker
                                        label="Start Date"
                                        v-model="localEvent.start"
                                        :error="!isStartDateValid"
                                        :error-messages="['Start date is required']"
                                    />
                                </VCol>

                                <VCol cols="12" v-show="eventTypeOptions.showAttributtes?.includes('endDate')">
                                    <CustomDatePicker
                                        label="End Date"
                                        v-model="localEvent.end"
                                        :error="!isEndDateValid"
                                        :error-messages="['End date is required']"
                                    />
                                </VCol>

                                <VCol cols="12" v-show="eventTypeOptions.showAttributtes?.includes('allDay')">
                                    <VSwitch
                                        color="primary"
                                        v-model="localEvent.allDay"
                                        label="All day"
                                    />
                                </VCol>

                                <VCol cols="12">
                                    <VTextField
                                        v-model="localEvent.extendedProps.location"
                                        label="Location"
                                        :rules="rules.location"
                                        variant="outlined"
                                        density="comfortable"
                                        prepend-inner-icon="mdi-map-marker"
                                    />
                                </VCol>

                                <VCol cols="12">
                                    <VTextarea
                                        v-model="localEvent.extendedProps.description"
                                        label="Description"
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
                                <span>Location</span>
                            </div>
                            <div class="info-content">
                                {{ localEvent?.extendedProps?.location || 'No location specified' }}
                            </div>
                        </div>

                        <!-- Description Info -->
                        <div class="event-info-item" v-if="localEvent?.extendedProps?.description">
                            <div class="info-label">
                                <v-icon color="primary" class="mr-2">mdi-text-box-outline</v-icon>
                                <span>Description</span>
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
                        <h3 class="text-h6 mb-3">Are you coming?</h3>
                        <div class="d-flex flex-wrap gap-2">
                            <VBtn
                                v-for="(answer, value) in eventTypeOptions.answers"
                                :key="`answer-${value}`"
                                :color="value === 'yes' ? 'success' : value === 'no' ? 'error' : 'primary'"
                                :variant="isGoing && isGoing.type === value ? 'elevated' : 'outlined'"
                                class="response-btn"
                                @click="joinEvent(value)"
                            >
                                <v-icon
                                    v-if="isGoing && isGoing.type === value"
                                    size="small"
                                    start
                                    class="me-1"
                                >
                                    mdi-check-circle
                                </v-icon>
                                {{ answer }}
                            </VBtn>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Attendees Section -->
                <v-card flat class="attendees-card mb-4" v-if="Object.keys(eventAnswers).length > 0">
                    <v-card-text>
                        <div class="d-flex align-center justify-space-between mb-3">
                            <h3 class="text-h6">Attendees</h3>
                            <v-btn
                                icon="mdi-information-outline"
                                variant="text"
                                color="primary"
                                density="comfortable"
                                size="small"
                                @click="openDialog()"
                                title="View detailed attendee list"
                            />
                        </div>

                        <div v-for="(guests, status) in filterGuestsByApproval(eventAnswers).approvedGuests"
                             :key="`status-${status}`"
                             class="mb-4">
                            <div class="d-flex align-center mb-2">
                                <v-chip
                                    :color="status === 'yes' ? 'success' : status === 'no' ? 'error' : 'primary'"
                                    size="small"
                                    class="me-2"
                                >
                                    {{ status }}
                                </v-chip>
                                <span class="text-subtitle-2">{{ guests.length }} people</span>
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

                <!-- Pending Approvals Section -->
                <v-card
                    flat
                    class="approval-card mb-4"
                    v-if="localEvent?.extendedProps?.user_id == user.id &&
                         eventTypeOptions.guest &&
                         eventTypeOptions.guest.includes('approval')"
                >
                    <v-card-text>
                        <h3 class="text-h6 mb-3">Pending Approvals</h3>

                        <div v-for="(guests, status) in filterGuestsByApproval(eventAnswers).guestsRequiringApproval"
                             :key="`pending-${status}`"
                             class="mb-4">
                            <v-list-subheader>{{ status }}</v-list-subheader>

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
                        <h3 class="text-h6 mb-3">Related Content</h3>

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
                                        {{ item.related.description || 'Related content' }}
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
        v-if="localEvent"
        v-model="showProfileDialog"
        :event="localEvent"
        :is-going="isGoing"
        :answer="profileAnswer"
        :resolve="handleProfile"
    />

    <ConfirmDialog
        v-model="showConfirmationDialog"
        title="Delete Event"
        content="Are you sure you want to delete this event? This action cannot be undone."
        confirmationText="Delete"
        cancellationText="Cancel"
        :resolve="handleConfirmation"
    />

    <RelatedContent
        v-model="showRelateContentDialog"
        contentName="Current Event"
        initialSourceType="App\Models\Event\Event"
        :initialSourceItem="String(localEvent?.id)"
        @confirmRelation="handleRelationConfirmed"
    />

    <DetailsDialog
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
