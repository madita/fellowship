<template>
    <div class="flex-grow-1 event-show">

        <v-skeleton-loader
            v-if="isLoading"
            class="ma-10"
            type="card-avatar, article, actions"
        ></v-skeleton-loader>

        <template v-else>
            <v-sheet
                class="event-hero"
                color="primary"
                width="100%"
                height="50vh"
            >

                <v-container class="fill-height pa-9">
                    <v-row>
                        <v-col>
                            <span>
                                {{ formatDate(event.startDate) }}
                                at {{ formatDate(event.startDate, userTimeFormat) }} - {{
                                    formatDate(event.endDate)
                                }} at {{ formatDate(event.endDate, userTimeFormat) }}
                            </span>

                            <h1 class="event-title">{{ event.title }}</h1>
                        </v-col>
                    </v-row>
                </v-container>

            </v-sheet>

            <v-container>
                <div class="calendar-day">
                    <div class="calendar-day-top"></div>
                    <div class="calendar-day-bottom">{{ formatDate(event.startDate, 'd') }}</div>
                </div>

                <v-row justify="center">
                    <v-col cols="8">
                        <div v-html="event.description"></div>
                    </v-col>
                    <v-col cols="4" class="align-content-end">
                        <div>Are you coming?</div>
                        <v-btn
                            color="primary"
                            class="me-3"
                            :disabled="getIsGoing('going')"
                            @click="register('going')"
                        >
                            Yes
                        </v-btn>
                        <v-btn
                            variant="tonal"
                            color="primary"
                            class="me-3"
                            :disabled="getIsGoing('notgoing')"
                            @click="register('notgoing')"
                        >
                            No
                        </v-btn>
                        <v-btn
                            variant="outlined"
                            color="secondary"
                            :disabled="getIsGoing('maybe')"
                            @click="register('maybe')"
                        >
                            Maybe
                        </v-btn>

                        <v-list-subheader>Is Going ({{ eventData?.going?.length || 0 }})</v-list-subheader>
                        <user-avatar
                            v-for="user in eventData?.going || []"
                            :key="`going-${user.id}`"
                            :user="user"
                        />

                        <v-list-subheader>Maybe Going ({{ eventData?.maybe?.length || 0 }})</v-list-subheader>
                        <user-avatar
                            v-for="user in eventData?.maybe || []"
                            :key="`maybe-${user.id}`"
                            :user="user"
                        />

                        <v-list-subheader>Not Going ({{ eventData?.notgoing?.length || 0 }})</v-list-subheader>
                        <user-avatar
                            v-for="user in eventData?.notgoing || []"
                            :key="`notgoing-${user.id}`"
                            :user="user"
                        />

                        <!-- Success Message -->
                        <v-alert
                            v-if="message"
                            type="success"
                            variant="tonal"
                            density="compact"
                            class="mt-4"
                            closable
                            @click:close="message = ''"
                        >
                            {{ message }}
                        </v-alert>
                    </v-col>
                </v-row>
            </v-container>
        </template>

    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useDateFormat } from '@/plugins/formatDate.js' // Adjust path as needed
import { useUserStore } from '@/store/userStore.js'
import { useSettingsStore } from '@/store/settingStore.js'
//import EventDatePicker from './EventDatePicker.vue'
import UserAvatar from '../common/UserAvatar.vue'
import axios from 'axios'

// Props (if any would be passed to this component)
const props = defineProps({
    // Add any props if needed
})

// Composables
const route = useRoute()
const { formatDate } = useDateFormat()
const userStore = useUserStore()
const settingsStore = useSettingsStore()

// Get user's time format preference (without seconds for display)
const userTimeFormat = computed(() => {
    const timeFormat = userStore.user?.time_format ||
                      settingsStore.appSettings?.time_format ||
                      'H:i:s';
    // Remove seconds for cleaner display
    return timeFormat.replace(':s', '').replace(' s', '');
})

// Reactive state
const isLoading = ref(true)
const eventData = ref(null)
const event = ref({ title: '', description: '' })
const endpoint = '/api/events'
const id = ref(null)
const message = ref('')

// Methods
const getEvent = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(`/api/events/${id.value}`)
        eventData.value = response.data
        event.value = response.data.event
        isLoading.value = false
    } catch (error) {
        console.error('Error fetching event:', error)
        isLoading.value = false
    }
}

const register = async (answer) => {
    try {
        const response = await axios.get(`${endpoint}/${id.value}/going/${answer}`)

        // Update the registration status
        if (eventData.value.isGoing) {
            eventData.value.isGoing.type = answer
        } else {
            eventData.value.isGoing = { type: answer }
        }

        // Update the participant lists
        eventData.value.going = response.data.going
        eventData.value.notgoing = response.data.notgoing
        eventData.value.maybe = response.data.maybe

        message.value = 'Answer saved'

        // Clear message after 3 seconds
        setTimeout(() => {
            message.value = ''
        }, 3000)

    } catch (error) {
        if (error.response?.status === 422) {
            console.error('Validation errors:', error.response.data)
            // Handle validation errors as needed
        }
        console.error('Error registering:', error)
    }
}

const getIsGoing = (answer) => {
    if (eventData.value === null) {
        return false
    }
    return eventData.value.isGoing !== undefined && eventData.value.isGoing.type === answer
}

// Lifecycle
onMounted(() => {
    if (route.params.id) {
        id.value = route.params.id
        getEvent()
    }
})
</script>

<style scoped>
.event-show {
    /* Add any specific styles if needed */
}

.event-hero {
    position: relative;
    font-size: large;
    color: whitesmoke !important;
}

.event-hero h1 {
    display: block;
    font-size: 6rem;
    color: white !important;
}

.calendar-day {
    position: relative;
    top: -3rem;
    width: 80px;
    height: 80px;
    box-shadow: inset 0 -3em 3em rgba(0, 0, 0, 0.1),
    0 0 0 0 rgb(255, 255, 255),
    0.3em 0.3em 1em rgba(0, 0, 0, 0.3);
    margin-bottom: -3rem;
}

.calendar-day-top {
    width: 80px;
    height: 20px;
    background: #941024;
}

.calendar-day-bottom {
    background-color: white;
    color: #1a202c;
    width: 80px;
    height: 60px;
    border-left: 1px solid #a2a2a2;
    border-right: 1px solid #a2a2a2;
    border-bottom: 1px solid #a2a2a2;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 600;
}

/* Responsive design */
@media (max-width: 768px) {
    .event-hero h1 {
        font-size: 3rem !important;
    }

    .calendar-day {
        width: 60px;
        height: 60px;
    }

    .calendar-day-top {
        width: 60px;
        height: 15px;
    }

    .calendar-day-bottom {
        width: 60px;
        height: 45px;
        font-size: 2rem;
    }
}

@media (max-width: 600px) {
    .event-hero {
        height: 40vh !important;
    }

    .event-hero h1 {
        font-size: 2rem !important;
    }
}
</style>
