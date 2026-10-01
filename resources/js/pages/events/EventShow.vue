<template>
    <div class="flex-grow-1 event-show">
        <v-container v-if="isLoading">
            <v-skeleton-loader type="heading, subtitle, article, actions" />
        </v-container>

        <empty-state
            v-else-if="loadFailed"
            icon="mdi-calendar-remove"
            :title="$t('events.loadError')"
        >
            <template #actions>
                <v-btn variant="tonal" :to="{ name: 'events' }">{{ $t('common.back') }}</v-btn>
            </template>
        </empty-state>

        <template v-else>
            <page-header
                :title="event.title"
                :subtitle="dateRange"
                icon="mdi-calendar"
                :back-to="{ name: 'events' }"
            >
                <template v-if="canEdit" #actions>
                    <v-btn
                        color="primary"
                        variant="elevated"
                        prepend-icon="mdi-pencil"
                        :to="{ name: 'event-edit', params: { id } }"
                    >
                        {{ $t('common.edit') }}
                    </v-btn>
                </template>
            </page-header>

            <v-container>
                <div class="calendar-day">
                    <div class="calendar-day-top"></div>
                    <div class="calendar-day-bottom">{{ formatDate(event.startDate, 'd') }}</div>
                </div>

                <v-row justify="center">
                    <v-col cols="12" md="8">
                        <div class="d-flex align-center font-weight-medium mb-1">
                            <v-icon color="primary" start>mdi-map-marker</v-icon>
                            <span>{{ $t('events.location') }}</span>
                        </div>
                        <div class="pl-8 mb-4">
                            <event-location-display :location="event.location" map />
                        </div>

                        <div v-html="event.description"></div>

                        <!-- Anything linked to this event: wiki pages,
                             albums, threads -->
                        <related-content-list
                            v-if="event.id"
                            class="mt-6"
                            type="App\Models\Event\Event"
                            :id="event.id"
                            :title="event.title"
                            :can-edit="canEdit"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <h2 class="text-h6 mb-3">{{ $t('events.areYouComing') }}</h2>

                        <!-- Who came is still worth reading once it is over;
                             answering is not. -->
                        <v-alert
                            v-if="hasEnded"
                            type="info"
                            variant="tonal"
                            density="compact"
                            class="mb-3"
                            :text="$t('events.eventOver')"
                        />

                        <div v-else class="d-flex flex-wrap ga-2">
                            <v-btn
                                color="primary"
                                variant="elevated"
                                :loading="answering === 'going'"
                                :disabled="getIsGoing('going') || (answering && answering !== 'going')"
                                @click="register('going')"
                            >
                                {{ $t('events.yes') }}
                            </v-btn>
                            <v-btn
                                variant="tonal"
                                color="primary"
                                :loading="answering === 'notgoing'"
                                :disabled="getIsGoing('notgoing') || (answering && answering !== 'notgoing')"
                                @click="register('notgoing')"
                            >
                                {{ $t('events.no') }}
                            </v-btn>
                            <v-btn
                                variant="outlined"
                                color="secondary"
                                :loading="answering === 'maybe'"
                                :disabled="getIsGoing('maybe') || (answering && answering !== 'maybe')"
                                @click="register('maybe')"
                            >
                                {{ $t('events.maybe') }}
                            </v-btn>
                        </div>

                        <v-list-subheader>{{ $t('events.isGoing') }} ({{ eventData?.going?.length || 0 }})</v-list-subheader>
                        <div class="d-flex flex-wrap ga-1">
                            <user-avatar
                                v-for="user in eventData?.going || []"
                                :key="`going-${user.id}`"
                                :user="user"
                            />
                        </div>

                        <v-list-subheader>{{ $t('events.maybeGoing') }} ({{ eventData?.maybe?.length || 0 }})</v-list-subheader>
                        <div class="d-flex flex-wrap ga-1">
                            <user-avatar
                                v-for="user in eventData?.maybe || []"
                                :key="`maybe-${user.id}`"
                                :user="user"
                            />
                        </div>

                        <v-list-subheader>{{ $t('events.notGoing') }} ({{ eventData?.notgoing?.length || 0 }})</v-list-subheader>
                        <div class="d-flex flex-wrap ga-1">
                            <user-avatar
                                v-for="user in eventData?.notgoing || []"
                                :key="`notgoing-${user.id}`"
                                :user="user"
                            />
                        </div>
                    </v-col>
                </v-row>
            </v-container>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDateFormat } from '@/plugins/formatDate.js' // Adjust path as needed
import { useUserStore } from '@/store/userStore.js'
import { useSettingsStore } from '@/store/settingStore.js'
//import EventDatePicker from '@/components/event/EventDatePicker.vue'
import UserAvatar from '@/components/common/UserAvatar.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import EventLocationDisplay from '@/components/event/EventLocationDisplay.vue'
import RelatedContentList from '@/components/common/RelatedContentList.vue'
import { hasEventEnded } from '@/utils/eventTime.js'
import axios from 'axios'
import { useDialog } from '@/composables/useDialog.js'

const { t } = useI18n()
const dialog = useDialog()

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
const loadFailed = ref(false)
const eventData = ref(null)
const event = ref({ title: '', description: '' })
const endpoint = '/api/events'
const id = ref(null)
// Which answer is currently being sent (null when idle)
const answering = ref(null)

// Same rule as EventController@update: the owner, or anyone with manage-posts
const canEdit = computed(() => {
    const me = userStore.user;
    if (!me?.id || !event.value?.id) return false;

    const permissions = userStore.permissions || [];

    return event.value.user_id === me.id
        || !!me.isAdmin
        || permissions.some(permission => (permission?.name ?? permission) === 'manage-posts');
});

/**
 * When the event happens, in as few words as it takes.
 *
 * An event with no startTime is an all-day one and has no clock to show —
 * formatting its date-only value with a time format only ever printed
 * "00:00". One that starts and ends on the same day names that day once.
 */
const dateRange = computed(() => {
    const from = event.value?.start || event.value?.startDate
    if (!from) return ''

    const to = event.value?.end || event.value?.endDate
    const allDay = !event.value?.startTime

    const day = value => formatDate(value)
    const time = value => formatDate(value, userTimeFormat.value)
    const sameDay = to && day(from) === day(to)

    if (allDay) {
        return sameDay || !to ? day(from) : `${day(from)} - ${day(to)}`
    }

    if (!to) return `${day(from)} ${time(from)}`

    return sameDay
        ? `${day(from)} ${time(from)} - ${time(to)}`
        : `${day(from)} ${time(from)} - ${day(to)} ${time(to)}`
})

// Answering is only possible while the event is still ahead — the same
// reading the drawer and Event::hasEnded() use.
const hasEnded = computed(() => !!event.value?.id && hasEventEnded(event.value));

// Methods
const getEvent = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(`/api/events/${id.value}`)
        eventData.value = response.data
        event.value = response.data.event
        isLoading.value = false
    } catch (error) {
        // Say so rather than rendering an empty page around a blank title
        isLoading.value = false
        loadFailed.value = true
        await dialog.requestError(error, t('events.loadError'))
    }
}

const register = async (answer) => {
    if (answering.value) return
    answering.value = answer
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

        answering.value = null
        await dialog.success(t('events.answerSaved'))
    } catch (error) {
        await dialog.requestError(error, t('events.rsvpError'))
    } finally {
        answering.value = null
    }
}

// A member who has not answered yet has isGoing null, not undefined — the
// difference used to throw mid-render and take the whole page with it.
const getIsGoing = (answer) => eventData.value?.isGoing?.type === answer

// Locale change handler
function onLocaleChange() {
    if (id.value) {
        getEvent()
    }
}

// Lifecycle
onMounted(() => {
    if (route.params.id) {
        id.value = route.params.id
        getEvent()
    }

    // Listen for locale changes to refetch content in new language
    window.addEventListener('locale-changed', onLocaleChange)
})

onUnmounted(() => {
    // Clean up locale change listener
    window.removeEventListener('locale-changed', onLocaleChange)
})
</script>

<style scoped>
/* Tear-off calendar leaf showing the day of the month; it overlaps the
   bottom edge of the page header band. */
.calendar-day {
    position: relative;
    top: -3rem;
    width: 80px;
    height: 80px;
    box-shadow: inset 0 -3em 3em rgba(var(--v-theme-on-surface), 0.1),
    0.3em 0.3em 1em rgba(var(--v-theme-on-surface), 0.3);
    margin-bottom: -3rem;
}

.calendar-day-top {
    width: 80px;
    height: 20px;
    background: rgb(var(--v-theme-primary));
}

.calendar-day-bottom {
    background-color: rgb(var(--v-theme-surface));
    color: rgb(var(--v-theme-on-surface));
    width: 80px;
    height: 60px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-top: 0;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 600;
}

/* Responsive design */
@media (max-width: 768px) {
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
</style>
