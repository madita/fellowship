import { sliceEvents, createPlugin } from '@fullcalendar/core';
import { i18n } from "@/plugins/vue-i18n.js";
import { eventBus } from "../common/eventBus.js";
import { formatDate } from "@/plugins/formatDate.js";
import { useUserStore } from "@/store/userStore.js";
import { useSettingsStore } from "@/store/settingStore.js";

// Helper function to get user's time format preference
function getUserTimeFormat() {
    const userStore = useUserStore();
    const settingsStore = useSettingsStore();

    const timeFormat = userStore.user?.time_format ||
                      settingsStore.appSettings?.time_format ||
                      'H:i:s';
    // Remove seconds for cleaner display
    return timeFormat.replace(':s', '').replace(' s', '');
}

const CustomViewConfig = {
    classNames: ['custom-view'],
    duration: { month: 1 },
    type: 'list',

    content: function (props) {
        let segs = sliceEvents(props, true); // allDay=true

        const eventsByDate = {};
        segs.forEach(seg => {
            const start = seg.range.start;
            const end = seg.range.end;
            let currentDate = new Date(start);
            const endDate = new Date(end);

            let cnt = 0;
            while (currentDate <= endDate) {
                let isStart = cnt === 0;
                let isEnd = currentDate.getTime() === endDate.getTime();
                const dateStr = currentDate.toISOString().split('T')[0];
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
                currentDate.setDate(currentDate.getDate() + 1);
                cnt++;
            }
        });

        let html = '<div class="view-title"></div>';
        if (segs.length > 0) {
            html += '<ul class="view-events">';
            Object.keys(eventsByDate).sort().forEach(date => {
                // Format date with user preferences
                const formattedDate = formatDate(new Date(date));
                const weekday = new Date(date).toLocaleDateString(i18n.locale, { weekday: 'long' });

                html += `<div class="event-list-custom fc-list-day-cushion fc-cell-shaded">
                  <a id="fc-dom-10" class="fc-list-day-text" aria-label="${formattedDate}">
                    ${formattedDate}
                  </a>
                  <a aria-hidden="true" class="fc-list-day-side-text" aria-label="${weekday}">
                    ${weekday}
                  </a>
                </div>
                <ul class="event-list-custom">`;

                eventsByDate[date].forEach(event => {
                    // Use formatDate with user's timezone and time format preferences
                    const timeFormat = getUserTimeFormat();
                    const startTime = formatDate(event.def.extendedProps.originDate?.start, timeFormat);
                    const endTime = formatDate(event.def.extendedProps.originDate?.end, timeFormat);
                    html += `<li>`;
                    if (event.def.allDay || (!event.isStart && !event.isEnd)) {
                        html += `<span class="fc-list-event-time">all-day</span>`;
                    } else {
                        html += `<span class="fc-list-event-time">`;
                        if (event.isStart) {
                            html += `<span class="fc-list-event-time">${startTime}</span>`;
                        }
                        if (event.isEnd) {
                            html += `- <span class="fc-list-event-time">${endTime}</span>`;
                        }
                    }
                    html += `<span class="fc-list-event-dot" :style="{'border-color': ${event.def.extendedProps.colorName}};"></span>`;
                    html += `<span class="fc-list-event-graphic"></span><span class="fc-list-event-title">
                    <a data-id="${event.id}" class="event-link fc-event fc-event-draggable fc-event-start">${event.title}</a>
                  </span></li>`;
                });
                html += '</ul>';
            });
        } else {
            html += '<div class="fc-list-empty"><div class="fc-list-empty-cushion">No events to display</div></div>';
        }

        return { html: html };
    },

    didMount: function (props) {
        const eventElements = props.el.querySelectorAll('.event-link');
        eventElements.forEach(element => {
            const handleClick = () => {
                const eventId = element.getAttribute('data-id');
                const event = { ...props.eventStore.defs[eventId] };
                if (event) {
                    event.start = event.extendedProps.originDate.start;
                    event.end = event.extendedProps.originDate.end;
                    event.id = event.publicId;
                    eventBus.emit('openSidebarWithEvent', event);
                }
            };

            element.addEventListener('click', handleClick);

            // Store the handle to remove it later
            element._handleClick = handleClick;
        });
    },

    willUnmount: function (props) {
        const eventElements = props.el.querySelectorAll('.event-link');
        eventElements.forEach(element => {
            if (element._handleClick) {
                element.removeEventListener('click', element._handleClick);
                // Clean up the stored reference
                delete element._handleClick;
            }
        });
    }
};

export default createPlugin({
    views: {
        custom: CustomViewConfig
    }
});
