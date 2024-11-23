<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import CustomDatePicker from "../common/CustomDatePicker.vue";
import UserAvatar from "../common/UserAvatar.vue";
import axios from "axios";
import { useCalendarStore } from '@/store/calendarStore.js';
import ConfirmDialog from '../common/ConfirmDialog.vue';
import RelatedContent from '../common/RelatedContent.vue';
import {useUserStore} from "@/store/userStore.js";

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

const showConfirmationDialog = ref(false);
const showRelateContentDialog = ref(false);


const calendarStore = useCalendarStore();
const localEditMode = ref(props.editMode);
const refForm = ref();
const isFocused = ref(true);
const loadEventDetails = ref(true);

const event = ref(props.event);
const localEventTypes = computed(() => calendarStore.eventTypes);
const eventAnswers = ref([]);
const isStartDateValid = ref(true);
const isEndDateValid = ref(true);

const confirmationDialog = ref(null);

const relatedItems = ref([]);

const userStore = useUserStore();

const openConfirmationDialog = () => {
    confirmationDialog.value.isOpen = true;
};



// const handleCancel = () => {
//     console.log('Deletion canceled');
// };


const eventTypeItems = computed(() => Object.values(localEventTypes.value));

const eventType = computed(() => {
    let type;

    type = Object.values(localEventTypes.value).find(item => item.name === event.value.extendedProps.type);

    return type;
});

const user = computed(() => {

    if(userStore.user) {
        return userStore.user
    }

    return {id:null};
});

const resetEvent = () => {
    event.value = JSON.parse(JSON.stringify(props.event));
    isStartDateValid.value = true;
    isEndDateValid.value = true;
    if (event.value.id) getEvent(event.value.id);
    if (event.value.id) fetchRelatedItems('App\\Models\\Event\\Event', event.value.id);
    nextTick(() => refForm.value?.resetValidation());
};

const canJoinEvent = computed(() => {
    if (!event.value.id) return false;
    const now = new Date();
    return event.value.end ? new Date(event.value.end) > now : new Date(event.value.start) > now;
});

const removeEvent = () => {
    emit('removeEvent', String(event.value.id));
    emit('update:isDrawerOpen', false);
};

const handleSubmit = () => {
    validateStartDate();
    validateEndDate();
    // refForm.value?.validate().then(({ valid }) => {
    //     if (valid) {
    //         emit(localEditMode.value ? 'updateEvent' : 'addEvent', event.value);
    //         emit('update:isDrawerOpen', false);
    //         localEditMode.value = false;
    //     }
    // });

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
};
const eventTypeOptions = computed(() => {
    let type

    // type = Object.values(localEventTypes.value).find(item => item.name ===  event.value.extendedProps.type);
    type = Object.values(localEventTypes.value).find(item => item.id === event.value.extendedProps.type_id);

    if(type === undefined)
        return []

    return JSON.parse(JSON.stringify(type.options));
});


const onCancel = () => {
    emit('update:isDrawerOpen', false);
}

const getEvent = async (eventId) => {
    try {
        loadEventDetails.value = true;
        const response = await axios.get(`/api/events/${eventId}`);

        eventAnswers.value = response.data.answers;

        //filter out not approved guests?
        // if (eventTypeOptions.value.guest && eventTypeOptions.value.guest.includes('approval')) {
        //
        //     for (const key in eventAnswers.value) {
        //         if (Array.isArray(eventAnswers.value[key])) {
        //             eventAnswers.value[key] = eventAnswers.value[key].filter(item => item.pivot && item.pivot.approved_at !== null);
        //         }
        //     }
        //
        // }
    } catch (err) {
        console.error('Failed to load event details:', err);
    } finally {
        loadEventDetails.value = false;
    }
};

const fetchRelatedItems = async (model, eventId) => {
    try {
        const response = await axios.post('/api/related-items', {
            modelType:model, modelId:eventId,
        });
        relatedItems.value = response.data.items
    } catch (error) {
        console.error('Failed to fetch related items:', error);
    }
};

const approveGuest = async (guestId, action) => {
    try {
        await axios.post(`/api/events/${event.value.id}/approve-guest`, {
            guestId,
            action,
        });

        // Update the event answers to reflect the changes
        eventAnswers.value = eventAnswers.value.map((group) =>
            group.map((guest) =>
                guest.id === guestId ? { ...guest, approved: action === "approve" } : guest
            )
        );

        console.log(`Guest ${action}d successfully`);
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

            //const guestWithStatus = { ...guest, status }; // Add the status to each guest

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
    isStartDateValid.value = !!event.value.start;
};

const validateEndDate = () => {
    isEndDateValid.value = !!event.value.end;
};

const joinEvent = (answer) => {
    axios.post(`/api/events/${event.value.id}/answer`, { answer })
        .catch((error) => {
            if (error.response.status === 422) console.error('Validation failed:', error);
        });
};

const dialogModelValueUpdate = (val) => {
    emit('update:isDrawerOpen', val);
};

const relateContent = () => {
    //console.log('related content')
};

const rules = {
    title: [v => !!v || 'Title is required'],
    date: [v => !!v || 'Date is required'],
    location: [v => !!v || 'Location is required'],
};

const handleConfirmation = (isConfirmed) => {
    if (isConfirmed) {
        // Perform the delete action
        console.log('Item deleted');
        removeEvent(event.value.id)
    } else {
        console.log('Action canceled');
    }
};

const handleRelationConfirmed = (relation) => {
    console.log('Relation confirmed:', relation);
    // Here, handle the relation (e.g., save it to a database, display it in the UI, etc.)
};

function isDateInPast(date) {
    const currentDate = new Date();
    return new Date(date) < currentDate;
}

watch(() => props.event, (newEvent) => {
    event.value = { ...newEvent };
}, { deep: true, immediate: true });

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
        class="scrollable-content"
        @update:model-value="dialogModelValueUpdate"
    >
        <div class="pa-2 d-flex align-center" v-if="localEditMode">
            <h5 class="text-h5 me-3">{{ event.id ? 'Update Event' : 'Add Event' }}</h5>
            <VSpacer />
            <VBtn v-if="event.id" color="primary" class="me-3" @click="localEditMode = !localEditMode">
                {{ localEditMode ? 'View' : 'Edit' }}
            </VBtn>
        </div>
        <div class="pa-2 d-flex align-center" v-else>
            <h5 class="text-h5 me-3">{{ event.title }}</h5>

            <VSpacer />

            <slot name="beforeClose" />


            <v-btn icon="mdi-pencil"
                   density="compact"
                   class="me-3"
                   @click="localEditMode = true">

            </v-btn>

            <v-btn icon="mdi-delete"
                   density="compact"
                   class="me-3"
                   @click="showConfirmationDialog = true">

            </v-btn>

            <v-btn icon="mdi-link-variant"
                   density="compact"
                   class="me-3"
                   @click="showRelateContentDialog = true"
                   aria-label="Relate Content">

            </v-btn>

            <v-btn icon="mdi-close"
                   density="compact"
                   @click="dialogModelValueUpdate(false)"
                   class="me-3"
                   aria-label="Close Drawer">

            </v-btn>

        </div>

        <VDivider />

        <PerfectScrollbar :options="{ wheelPropagation: false }">
            <VCard flat v-if="localEditMode">
                <VCardText>
                    <VForm ref="refForm" @submit.prevent="handleSubmit">
                        <VRow>
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
                                <VTextField v-model="event.title" label="Title" :rules="rules.title" />
                            </VCol>

                            <VCol cols="12">
                                <CustomDatePicker
                                    label="Start Date"
                                    v-model="event.start"
                                    :error="!isStartDateValid"
                                    :error-messages="['Start date is required']"
                                />
                            </VCol>

                            <VCol cols="12" v-show="eventTypeOptions.showAttributtes.includes('endDate')">
                                <CustomDatePicker
                                    label="End Date"
                                    v-model="event.end"
                                    :error="!isEndDateValid"
                                    :error-messages="['End date is required']"
                                />
                            </VCol>

                            <VCol cols="12" v-show="eventTypeOptions.showAttributtes.includes('allDay')">
                                <VSwitch
                                    color="primary"
                                    v-model="event.allDay"
                                    label="All day"
                                />
                            </VCol>

                            <VCol cols="12">
                                <VTextField v-model="event.extendedProps.location" label="Location" :rules="rules.location" />
                            </VCol>

                            <VCol cols="12">
                                <VTextarea v-model="event.extendedProps.description" label="Description" />
                            </VCol>
                            </template>


                            <VCol cols="12" class="d-flex justify-end">
                                <VBtn type="submit" class="me-3">Submit</VBtn>
                                <VBtn variant="outlined" color="secondary" @click="onCancel">Cancel</VBtn>
                            </VCol>
                        </VRow>
                    </VForm>
                </VCardText>
            </VCard>

            <VCard flat v-else>
                <VCardText>
                    <VRow>
                        <VCol cols="12" v-if="canJoinEvent">

                            <div>Are you coming?</div>
                            <template v-if="eventType">

                                <VBtn v-for="(answer, value) in eventTypeOptions.answers"
                                      :key="`answer-${value}`"
                                      class="me-3"

                                      @click="joinEvent(value)">
                                    {{answer}}

                                </VBtn>
                            </template>

                        </VCol>

                        <VCol cols="12">
                            <label>Date/Time</label>
                            {{ new Date(event.start).toLocaleDateString() }} - {{ new Date(event.end).toLocaleDateString() }}
                        </VCol>

                        <VCol cols="12">
                            <label>Location</label>
                            {{  event.extendedProps.location }}
                        </VCol>

                        <VCol cols="12">
                            <label>Description</label>
                            <div v-html="event.extendedProps.description"></div>
                        </VCol>

<!--                        <VCol cols="12">-->
<!--                            <template v-for="(answer, value) in eventAnswers">-->
<!--                                <v-list-subheader>{{ value }} ({{ answer.length }})</v-list-subheader>-->
<!--                                <UserAvatar v-for="user in answer" :key="`going-${user.id}`" :user="user" />-->
<!--                            </template>-->
<!--                        </VCol>-->

                        <VCol cols="12">
                            <template v-for="(guests, status) in filterGuestsByApproval(eventAnswers).approvedGuests">
                                <v-list-subheader>{{ status }} ({{ guests.length }})</v-list-subheader>
                                <v-list>
                                    <v-list-item
                                        v-for="guest in guests"
                                        :key="guest.id"
                                    >
                                        <UserAvatar :user="guest" />
                                    </v-list-item>
                                </v-list>
                            </template>
                        </VCol>

                        <VCol cols="12" v-if="event.extendedProps.user_id == user.id && eventTypeOptions.guest && eventTypeOptions.guest.includes('approval')">
                            <h3>Guests Requiring Approval</h3>
                            <template v-for="(guests, status) in filterGuestsByApproval(eventAnswers).guestsRequiringApproval">
                                <v-list-subheader>{{ status }}</v-list-subheader>
                                <v-list>
                                    <v-list-item
                                        v-for="guest in guests"
                                        :key="guest.id"
                                    >

                                        <UserAvatar :user="guest" />
                                        <v-list-item-action>
                                            <v-btn
                                                color="green"
                                                @click="approveGuest(guest.pivot.user_id, 'approve')"
                                            >
                                                Approve
                                            </v-btn>
                                            <v-btn
                                                color="red"
                                                @click="approveGuest(guest.pivot.user_id, 'reject')"
                                            >
                                                Reject
                                            </v-btn>
                                        </v-list-item-action>
                                    </v-list-item>
                                </v-list>
                            </template>
                        </VCol>


                        <VCol cols="12">
<!--                            <h3>Related Content</h3>-->

                                    <v-card v-for="item in relatedItems" :key="item.id"
                                        class="card-hover"
                                            elevation="10"
                                            rounded="md"
                                            :to="`/gallery/${item.related.slug}`"
                                            :class="`v-theme--ORANGE_THEME`"
                                    >

                                        <v-img v-if="item.related.coverImage" :src="item.related.coverImage" height="200"></v-img>
                                        <v-card-text>
                                            <div class="d-flex align-center gap-3">
                                                <div>
                                                    <h6 class="text-h6 mb-1">{{ item.related.title }}</h6>
                                                    <span class="d-block text-truncate d-flex align-center gap-2 textSecondary"></span>
                                                </div>
                                            </div>
                                        </v-card-text>
                                    </v-card>



<!--                                    <router-link :to="`/gallery/${item.related.slug}`">{{ item.related.title }}</router-link>-->

                        </VCol>
                    </VRow>
                </VCardText>
            </VCard>
        </PerfectScrollbar>
    </VNavigationDrawer>
    <!-- Confirmation Dialog Component -->
    <ConfirmDialog
        v-model="showConfirmationDialog"
        title="Delete Confirmation"
        content="Are you sure you want to delete this item?"
        confirmationText="Delete"
        cancellationText="Cancel"
        :resolve="handleConfirmation"
    />

    <RelatedContent
        v-model="showRelateContentDialog"
        contentName="Current Event"
        initialSourceType="App\Models\Event\Event"
        :initialSourceItem="event.id"
        @confirmRelation="handleRelationConfirmed"
    />
</template>

<style scoped>
.scrollable-content {
    max-height: 100%;
}
</style>
