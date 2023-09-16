import { default as axios } from 'axios'
import { default as _ } from 'lodash'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window._ = _
window.axios = axios
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// axios.defaults.withCredentials = true;

// window.route = require('./helper/route');
/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */


Pusher.logToConsole = true;

window.Echo = new Echo({
    broadcaster: "pusher",
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    encrypted: true,
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    forceTLS: true,
    authorizer: (channel) => {
        return {
            authorize: (socketId, callback) => {
                axios.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name
                })
                    .then(response => {
                        console.log('callback', response)
                        callback(false, response.data);
                    })
                    .catch(error => {
                        console.log('error', error)
                        callback(true, error);
                    });
            }
        };
    },
})
