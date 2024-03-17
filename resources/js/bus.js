// import * as Vue from 'vue'
//
// export default new Vue()
//-----
// import { ref, readonly, onMounted, onBeforeUnmount } from 'vue';
//
// export function createEventBus() {
//     const events = ref({});
//
//     function emit(event, ...args) {
//         if (events.value[event]) {
//             for (let handler of events.value[event]) {
//                 handler(...args);
//             }
//         }
//     }
//
//     function on(event, handler) {
//         if (!events.value[event]) {
//             events.value[event] = [];
//         }
//
//         events.value[event].push(handler);
//
//         onMounted(() => {
//             const currentEvents = events.value[event];
//             const index = currentEvents.indexOf(handler);
//             if (index !== -1) {
//                 currentEvents.splice(index, 1);
//             }
//         });
//     }
//
//     return {
//         emit,
//         on: readonly(on)
//     };
// }
//
// const EventBus = createEventBus();
//
// export default EventBus;

import { ref, onMounted } from 'vue';

export default function useEventBus() {
    const events = ref({});

    function on(event, handler) {
        console.log('event', event)
        console.log('handler', handler)

        if (!events.value[event]) {
            events.value[event] = [];
        }

        events.value[event].push(handler);

        onMounted(() => {
            const currentEvents = events.value[event];
            const index = currentEvents.indexOf(handler);
            if (index !== -1) {
                currentEvents.splice(index, 1);
            }
        });
    }

    function emit(event, ...args) {
        if (events.value[event]) {
            events.value[event].forEach(handler => handler(...args));
        }
    }

    return {
        on,
        emit
    };
}
