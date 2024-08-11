import { defineStore } from 'pinia'
import { useApi } from '@/api/useAPI.js'
// import axios from "axios";

const api = useApi()

export const useCalendarStore = defineStore({
    id: 'calendar',
    state: () => ({
        eventTypes: [],
        selectedEventTypes: [],

    }),
    actions: {
        setEventTypes(eventTypes) {
            this.eventTypes = eventTypes

            this.selectedEventTypes = Object.values(eventTypes).map(i => i.name)

        },

        // async fetchEvents() {
        //     const { data, error } = await useApi(createUrl('/apps/calendar', {
        //         query: {
        //             calendars: this.selectedCalendars,
        //         },
        //     }))
        //
        //     if (error.value)
        //         return error.value
        //
        //     return data.value
        // },
        // async addEvent(event) {
        //     await $api('/apps/calendar', {
        //         method: 'POST',
        //         body: event,
        //     })
        // },
        // async updateEvent(event) {
        //     return await $api(`/apps/calendar/${event.id}`, {
        //         method: 'PUT',
        //         body: event,
        //     })
        // },
        // async removeEvent(eventId) {
        //     return await $api(`/apps/calendar/${eventId}`, {
        //         method: 'DELETE',
        //     })
        // },
    }
});
