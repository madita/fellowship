import { sliceEvents, createPlugin } from '@fullcalendar/core';
import  { i18n } from "@/plugins/vue-i18n.js";
import {eventBus} from "../common/eventBus.js";

const CustomViewConfig = {
    classNames: ['custom-view'],
    duration: { month: 1 },
    type: 'list',
    // eventClick: function(event) {
    //     console.log('testcustom', event)
    // },

    content: function(props) {
        // console.log('props', props);

        let segs = sliceEvents(props, true); // allDay=true
        // console.log('eventscustom', segs);

        // Group events by each day they span
        const eventsByDate = {};
        segs.forEach(seg => {
            // console.log('seg', seg)
            // console.log('seg',seg)
            const start = seg.range.start;
            const end = seg.range.end;
            // console.log('hmmmstart',start,seg.range)
            let currentDate = new Date(start); // start day at midnight
            const endDate = new Date(end); // end day at midnight

            let cnt = 0;
            while (currentDate <= endDate) {
                // console.log(cnt, currentDate, endDate)
                let isStart = cnt === 0 ? true:false;
                let isEnd = currentDate.getTime() === endDate.getTime() ? true:false;
                // console.log('isEnd',isEnd)
                const dateStr = currentDate.toISOString().split('T')[0];
                // console.log('dateStr', dateStr, currentDate.toISOString())
                if (!eventsByDate[dateStr]) {
                    eventsByDate[dateStr] = [];
                }
                eventsByDate[dateStr].push({
                    id: seg.def.defId,
                    title: seg.def.title,
                    start: seg.range.start,
                    end: seg.range.end,
                    def: seg.def,
                    isStart: isStart,
                    isEnd: isEnd
                });
                // Move to the next day
                currentDate.setDate(currentDate.getDate() + 1);
                cnt++;
                // if(currentDate === endDate) {
                //
                // }
            }
        });

        let html = '<div class="view-title"></div>';

        if(segs.length > 0) {
            html += '<ul class="view-events">';

            // Sort dates and generate HTML content
            Object.keys(eventsByDate).sort().forEach(date => {

                html += `<!--<li class="event-day">${new Date(date).toDateString()} - ${eventsByDate[date].length} events</li><ul class="event-list">-->`;
                html += `<div class="event-list-custom fc-list-day-cushion fc-cell-shaded"><a id="fc-dom-10" class="fc-list-day-text" aria-label="${new Date(date).toDateString()}">${new Date(date).toDateString()}</a><a aria-hidden="true" class="fc-list-day-side-text" aria-label="${new Date(date).toDateString()}">${new Date(date).toLocaleDateString(i18n.locale, { weekday: 'long' })}</a></div><ul class="event-list-custom">`;
                eventsByDate[date].forEach(event => {
                    console.log('event',event)

                    const startTime = new Date(event.def.extendedProps.originDate.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const endTime = new Date(event.def.extendedProps.originDate.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    html += `<li>`;
                    if(event.def.allDay === true || (!event.isStart && !event.isEnd)) {
                        html += `<span class="fc-list-event-time">all-day</span>`;
                    } else {
                        if(event.isStart) {
                            html += `<span class="fc-list-event-time">${startTime}</span>`;
                        }
                        if(event.isEnd) {
                            html += `<span class="fc-list-event-time">${endTime}</span>`;
                        }
                        // html += `<span class="fc-list-event-time">${startDate}</span>`;
                    }
                    html += `<span class="fc-list-event-dot" style="border-color: ${event.def.extendedProps.colorName};"></span>`;
                    html += `<span class="fc-list-event-graphic"></span><span class="fc-list-event-title"><a data-id="${event.id}" class="event-link fc-event fc-event-draggable fc-event-start">${event.title}</a></span></li>`;
                    // html += `<li>${event.title} (${startDate} to ${endDate})</li>`;
                });
                html += '</ul>';
            });
        } else {
            html += '<div class="fc-list-empty"><div class="fc-list-empty-cushion">No events to display</div></div>';
        }

        // Construct HTML




        // html += '</ul>';

        return { html: html }
    },

    didMount: function(props) {
        // console.log('custom view now loaded');
        // console.log('customviewprops',props)
        const eventElements = props.el.querySelectorAll('.event-link');
        eventElements.forEach(element => {
            element.addEventListener('click', function() {
                const eventId = this.getAttribute('data-id');
                // console.log('args', props)
                // console.log('element', element)
                const event = {...props.eventStore.defs[eventId]};

                // console.log('event', event);
                if (event) {
                    event.start = event.extendedProps.originDate.start
                    event.end = event.extendedProps.originDate.end

                    // console.log('customevent',eventId,event)
                    // emit('update:isDrawerOpen', true)
                    eventBus.emit('openSidebarWithEvent', event);
                }
            });
        });
    },

    willUnmount: function(props) {
        // console.log('about to change awa?y from custom view');
        // Cleanup: remove event listeners if necessary
        const eventElements = props.el.querySelectorAll('.event-link');
        eventElements.forEach(element => {
            element.removeEventListener('click', function() {
                console.log('removeeventlistener')
            });
        });
    }
}

// const handleEventClick = () => {
//     console.log('testcustom')
// }

export default createPlugin({
    views: {
        custom: CustomViewConfig
    }
});
