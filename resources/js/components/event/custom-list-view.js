import { sliceEvents, createPlugin } from '@fullcalendar/core';
import  { i18n } from "@/plugins/vue-i18n.js";

const CustomViewConfig = {
    classNames: ['custom-view'],
    duration: { month: 1 },
    type: 'list',

    content: function(props) {
        // console.log('props', props);

        let segs = sliceEvents(props, true); // allDay=true
        // console.log('eventscustom', segs);

        // Group events by each day they span
        const eventsByDate = {};
        segs.forEach(seg => {
            // console.log('seg',seg)
            const start = seg.range.start;
            const end = seg.range.end;
            // console.log('hmmmstart',start,seg.range)
            let currentDate = new Date(start); // start day at midnight
            const endDate = new Date(end); // end day at midnight

            while (currentDate <= endDate) {
                const dateStr = currentDate.toISOString().split('T')[0];
                // console.log('dateStr', dateStr, currentDate.toISOString())
                if (!eventsByDate[dateStr]) {
                    eventsByDate[dateStr] = [];
                }
                eventsByDate[dateStr].push({
                    title: seg.def.title,
                    start: seg.range.start,
                    end: seg.range.end,
                    def: seg.def
                });
                // Move to the next day
                currentDate.setDate(currentDate.getDate() + 1);
            }
        });

        // Construct HTML
        let html = '<div class="view-title">' +

            '</div><ul class="view-events">';

        // Sort dates and generate HTML content
        Object.keys(eventsByDate).sort().forEach(date => {

            html += `<!--<li class="event-day">${new Date(date).toDateString()} - ${eventsByDate[date].length} events</li><ul class="event-list">-->`;
            html += `<div class="event-list-custom fc-list-day-cushion fc-cell-shaded"><a id="fc-dom-10" class="fc-list-day-text" aria-label="${new Date(date).toDateString()}">${new Date(date).toDateString()}</a><a aria-hidden="true" class="fc-list-day-side-text" aria-label="${new Date(date).toDateString()}">${new Date(date).toLocaleDateString(i18n.locale, { weekday: 'long' })}</a></div><ul class="event-list-custom">`;
            eventsByDate[date].forEach(event => {
                // console.log('event',event)

                const startDate = new Date(event.def.extendedProps.originDate.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const endDate = new Date(event.def.extendedProps.originDate.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                html += `<li>`;
                if(event.def.allDay === true) {
                    html += `<span class="fc-list-event-time">all-day</span>`;
                } else {
                    html += `<span class="fc-list-event-time">${startDate}</span>`;
                }
                html += `<span class="fc-list-event-graphic"></span><span class="fc-list-event-title">${event.title}</span></li>`;
                // html += `<li>${event.title} (${startDate} to ${endDate})</li>`;
            });
            html += '</ul>';
        });

        // html += '</ul>';

        return { html: html }
    },

    didMount: function(props) {
        // console.log('custom view now loaded');
    },

    willUnmount: function(props) {
        // console.log('about to change away from custom view');
    }
}

export default createPlugin({
    views: {
        custom: CustomViewConfig
    }
});
