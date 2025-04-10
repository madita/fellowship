// store/calendarStore.js
import { defineStore } from 'pinia';
import axios from 'axios';

export const useCalendarStore = defineStore('calendar', {
    state: () => ({
        events: [],
        upcomingEvents: [],
        eventTypes: [],
        selectedEventTypes: [],
        loading: false,
        error: null,
        currentViewType: 'dayGridMonth',
        currentViewDateRange: {
            start: null,
            end: null
        }
    }),

    actions: {
        /**
         * Set the current view date range based on the calendar current view
         * @param {Object} dateInfo - Contains start and end dates from FullCalendar
         */
        setViewDateRange(dateInfo) {
            this.currentViewDateRange = {
                start: dateInfo.startStr.split('T')[0], // Get just the date part
                end: dateInfo.endStr.split('T')[0]
            };
        },

        /**
         * Fetch events for the current calendar view
         */
        async fetchEvents() {
            this.loading = true;
            this.error = null;

            try {
                // Only send date range if we have one
                const params = {};

                if (this.currentViewDateRange.start && this.currentViewDateRange.end) {
                    params.start_date = this.currentViewDateRange.start;
                    params.end_date = this.currentViewDateRange.end;
                    params.view_type = this.currentViewType;
                }

                const response = await axios.get('/api/events', { params });

                if (response.data.data) {
                    this.events = response.data.data.events || [];

                    // If event types are returned here, update them
                    if (response.data.data.types) {
                        this.setEventTypes(response.data.data.types);
                    }
                }
            } catch (error) {
                console.error('Error fetching events:', error);
                this.error = 'Failed to fetch events. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        /**
         * Fetch upcoming events for display in the sidebar and overview
         * @param {Number} days - Number of days to look ahead (default: 7)
         * @param {Number} limit - Maximum number of events to return (default: 10)
         */
        async fetchUpcomingEvents(days = 7, limit = 10) {
            try {
                const response = await axios.get('/api/events/upcoming', {
                    params: { days, limit }
                });

                if (response.data.data && response.data.data.events) {
                    this.upcomingEvents = response.data.data.events;
                }
            } catch (error) {
                console.error('Error fetching upcoming events:', error);
            }
        },

        /**
         * Fetch event types
         */
        async fetchEventTypes() {
            try {
                const response = await axios.get('/api/events/types');
                if (response.data.data) {
                    this.setEventTypes(response.data.data);
                }
            } catch (error) {
                console.error('Error fetching event types:', error);
            }
        },

        /**
         * Set event types and initialize selected types
         * @param {Object} types - Event types from the API
         */
        setEventTypes(types) {
            this.eventTypes = types;

            // Convert types to array if it's an object
            const typesArray = Array.isArray(types) ? types : Object.values(types);

            // If we haven't selected any types yet, select all by default
            if (this.selectedEventTypes.length === 0 && typesArray.length > 0) {
                this.selectedEventTypes = typesArray.map(type => type.name);
            }
        },

        /**
         * Set the current view type
         * @param {String} viewType - The FullCalendar view type
         */
        setCurrentViewType(viewType) {
            this.currentViewType = viewType;
        },

        /**
         * Refresh all calendar data
         */
        async refreshCalendarData() {
            await Promise.all([
                this.fetchEvents(),
                this.fetchUpcomingEvents(),
                this.fetchEventTypes()
            ]);
        }
    }
});
