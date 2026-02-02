import { default as axios } from 'axios'
import { default as _ } from 'lodash'
// import Echo from 'laravel-echo'
// import Pusher from 'pusher-js'


/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window._ = _
window.axios = axios
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
window.axios.defaults.headers.common['Accept'] = 'application/json'
window.axios.defaults.withCredentials = true

// Set CSRF token for axios requests
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// window.route = require('./helper/route');
/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */


// Pusher.logToConsole = true;

// window.Pusher = Pusher;
//
//
//
// window.Echo = new Echo({
//     broadcaster: "pusher",
//     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
//     encrypted: true,
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     forceTLS: true,
//     authorizer: (channel) => {
//         return {
//             authorize: (socketId, callback) => {
//                 axios.post('/broadcasting/auth', {
//                     socket_id: socketId,
//                     channel_name: channel.name
//                 })
//                     .then(response => {
//                         // console.log('callback', response)
//                         callback(false, response.data);
//                     })
//                     .catch(error => {
//                         console.log('error', error)
//                         callback(true, error);
//                     });
//             }
//         };
//     },
// })

import Echo from '@ably/laravel-echo';
import * as Ably from 'ably';

// window.Ably = require('ably');
window.Ably = Ably;

// Create new echo client instance using ably-js client driver.
// Note: broadcasting/auth is excluded from CSRF verification in VerifyCsrfToken middleware
// This prevents CSRF token mismatch errors after login when the session is regenerated
window.Echo = new Echo({
    broadcaster: 'ably',
    key: import.meta.env.VITE_ABLY_PUBLIC_KEY,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    }
});

// Register a callback for listing to connection state change
window.Echo.connector.ably.connection.on((stateChange) => {
    console.log("LOGGER:: Connection event :: ", stateChange);
    if (stateChange.current === 'disconnected' && stateChange.reason?.code === 40142) { // key/token status expired
        console.log("LOGGER:: Connection token expired https://help.ably.io/error/40142");
    }
});
