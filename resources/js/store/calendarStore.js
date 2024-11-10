import { defineStore } from 'pinia'
import { useApi } from '@/api/useAPI.js'
import axios from "axios";
// import axios from "axios";

// const api = useApi()

export const useCalendarStore = defineStore({
    id: 'calendar',
    state: () => ({
        eventTypes: [],
        events: [],
        selectedEventTypes: [],
        event:[],

    }),
    actions: {
        setEventTypes(eventTypes) {
            this.eventTypes = eventTypes

            this.selectedEventTypes = Object.values(eventTypes).map(i => i.name)

        },

        async fetchEvents() {
            console.log('fetchEventtypesAAAA')
            try {
                const response = await axios.get('/api/events');
                // console.log('events', response)
                const events = response.data.data.events.map(event => ({
                    ...event,
                    start: new Date(event.start),
                    end: new Date(event.end),
                }));

                this.events = events

                // return events; // Returns the processed events array.
            } catch (error) {
                console.error("Error fetching events:", error);
                return []; // Return an empty array in case of an error.
            }
        },

        async fetchEventTypes(){
            // loadEventDetails.value = true;
            // error.value = null; // Reset previous errors


            axios.get('/api/events/types').then((response) => {
                // messages = response.data;
                this.setEventTypes(response.data.data)
                //this.messages = chatStore.messages
            });

        },

        async getEventasync(eventId) {
            try {
                // loadEventDetails.value = true;
                const response = await axios.get(`/api/events/${eventId}`);
                this.event = response.data
                // loadEventDetails.value = false;
                // console.log('fetchevents',events)
                return this.event; // Returns the processed events array.
            } catch (error) {
                console.error("Error fetching event:", error);
                return []; // Return an empty array in case of an error.
            }
        },

        async addEvent(addevent) {

            axios.post(`${endpoint}`, addevent).then(() => {
                this.event = null
                // this.page = {title: "", body: ""};
                // this.message = "Page saved ..link"
            }).catch((error) => {
                if (error.response.status === 422) {
                    // this.creating.errors = error.response.data
                    this.editing.errors = error.response.data
                }
            })
        }

        // createEvent()  {
        //     isEventHandlerSidebarActive.value = true
        //     editMode.value = true
        // },



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
