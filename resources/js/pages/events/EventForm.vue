<template>
    <div class="flex-grow-1">
        <page-header
            :title="heading"
            icon="mdi-calendar-edit"
            :back-to="backTo"
        >
            <template #actions>
                <v-btn
                    color="primary"
                    variant="elevated"
                    :loading="saving"
                    prepend-icon="mdi-content-save"
                    @click="save"
                >
                    {{ heading }}
                </v-btn>
            </template>
        </page-header>

        <loading-state v-if="loading" />

        <v-container v-else>
            <v-form ref="formRef" @submit.prevent="save">
                <v-row>
                    <v-col cols="12" md="8">
                        <v-text-field
                            v-model="event.title"
                            :label="$t('common.title')"
                            :rules="[v => !!v || $t('common.required')]"
                            variant="outlined"
                            density="comfortable"
                            :disabled="saving"
                        />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-select
                            v-model="event.event_type_id"
                            :items="eventTypes"
                            :item-title="item => translateTypeName(item.name)"
                            :item-value="item => item.id"
                            :label="$t('events.eventType')"
                            :rules="[v => !!v || $t('common.required')]"
                            variant="outlined"
                            density="comfortable"
                            :disabled="saving"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-switch
                            v-model="event.allDay"
                            :label="$t('events.allDay')"
                            color="primary"
                            hide-details
                            density="compact"
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="event.start"
                            :label="$t('events.startDate')"
                            :type="event.allDay ? 'date' : 'datetime-local'"
                            :rules="[v => !!v || $t('common.required')]"
                            variant="outlined"
                            density="comfortable"
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="event.end"
                            :label="$t('events.endDate')"
                            :type="event.allDay ? 'date' : 'datetime-local'"
                            :rules="endRules"
                            variant="outlined"
                            density="comfortable"
                        />
                    </v-col>

                    <v-col cols="12">
                        <div class="text-subtitle-2 font-weight-medium mb-2">
                            {{ $t('events.location') }}
                        </div>
                        <!-- The same field the calendar drawer uses, with
                             room for the map to actually be usable -->
                        <event-location-field
                            :location="event.location"
                            :allowed-modes="allowedLocationModes"
                        />
                    </v-col>

                    <v-col cols="12">
                        <div class="text-subtitle-2 font-weight-medium mb-2">
                            {{ $t('common.description') }}
                        </div>
                        <tiptap v-model="event.description" />
                    </v-col>
                </v-row>

                <div class="d-flex justify-end ga-2 mt-4">
                    <v-btn variant="text" :disabled="saving" :to="backTo">
                        {{ $t('common.cancel') }}
                    </v-btn>
                    <v-btn color="primary" variant="elevated" :loading="saving" @click="save">
                        {{ heading }}
                    </v-btn>
                </div>
            </v-form>
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import Tiptap from '@/components/common/tiptap/Tiptap.vue';
import EventLocationField from '@/components/event/EventLocationField.vue';
import { useDialog } from '@/composables/useDialog.js';
import { useCalendarStore } from '@/store/calendarStore.js';
import { blankLocation, normalizeLocation } from '@/utils/eventLocation.js';

const { t, te } = useI18n();
const dialog = useDialog();

// Type names are seeded in English; show a translation when there is one
const translateTypeName = (name) => {
    const key = `events.types.${String(name || '').toLowerCase()}`;
    return te(key) ? t(key) : name;
};
const route = useRoute();
const router = useRouter();
const calendarStore = useCalendarStore();

const id = computed(() => route.params.id || null);
const loading = ref(false);
const saving = ref(false);
const formRef = ref(null);

const event = ref({
    title: '',
    description: '',
    event_type_id: null,
    start: '',
    end: '',
    allDay: false,
    location: blankLocation(),
});

const heading = computed(() => (id.value ? t('events.updateEvent') : t('events.addEvent')));

// Back where the member came from: the event's page when editing one,
// otherwise the calendar
const backTo = computed(() => (id.value
    ? { name: 'event-show', params: { id: id.value } }
    : { name: 'events' }));

const eventTypes = computed(() => Object.values(calendarStore.eventTypes || {}));

// Which location modes the chosen type allows, from its options.location
const allowedLocationModes = computed(() => {
    const type = eventTypes.value.find(item => item.id === event.value.event_type_id);
    let options = type?.options;

    if (typeof options === 'string') {
        try {
            options = JSON.parse(options);
        } catch {
            options = {};
        }
    }

    const modes = options?.location;

    return Array.isArray(modes) && modes.length ? modes : ['custom'];
});

const endRules = computed(() => [
    value => !value || !event.value.start || value >= event.value.start || t('events.endBeforeStart'),
]);

async function load() {
    loading.value = true;
    try {
        if (!eventTypes.value.length) await calendarStore.fetchEventTypes();

        if (!id.value) return;

        const { data } = await axios.get(`/api/events/${id.value}`);
        const found = data.event ?? data.data?.event ?? data;

        // An event with no time of day is an all-day event; the show
        // endpoint says so by leaving startTime null rather than by
        // sending an allDay flag.
        const allDay = found.allDay ?? (found.startTime == null);

        event.value = {
            title: found.title || '',
            description: found.description || '',
            event_type_id: found.event_type_id ?? null,
            start: toInputValue(found.start ?? found.startDate, allDay),
            end: toInputValue(found.end ?? found.endDate, allDay),
            allDay: Boolean(allDay),
            location: normalizeLocation(found.location),
        };
    } catch (error) {
        // Drop the spinner before the dialog: awaiting it here would leave
        // the page as a bare header for as long as the dialog stood open.
        loading.value = false;
        await dialog.requestError(error, t('events.loadError'));
    } finally {
        loading.value = false;
    }
}

/**
 * The date inputs want "YYYY-MM-DD" or "YYYY-MM-DDTHH:mm", not whatever
 * shape the API happens to send.
 */
function toInputValue(value, allDay) {
    if (!value) return '';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return String(value).slice(0, allDay ? 10 : 16);

    const pad = number => String(number).padStart(2, '0');
    const day = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

    return allDay ? day : `${day}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

async function save() {
    if (saving.value) return;

    const { valid } = await formRef.value.validate();
    if (!valid) return;

    saving.value = true;
    try {
        const payload = {
            title: event.value.title,
            description: event.value.description,
            event_type_id: event.value.event_type_id,
            start: event.value.start,
            end: event.value.end,
            allDay: event.value.allDay,
            extendedProps: { location: event.value.location },
        };

        const saved = id.value
            ? await axios.patch(`/api/events/${id.value}`, payload)
            : await axios.post('/api/events', payload);

        await dialog.success(id.value ? t('events.eventUpdated') : t('events.eventCreated'));

        const savedId = id.value || saved.data?.event?.id || saved.data?.data?.id || saved.data?.id;

        router.push(savedId ? { name: 'event-show', params: { id: savedId } } : { name: 'events' });
    } catch (error) {
        await dialog.requestError(error, t('events.saveError'));
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
