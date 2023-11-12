// import store from '../../store'

// export default function guest ({ to, from, store, next }){
//     const isLogged = store.getters['auth/authenticated'];
//
//     if(isLogged){
//         return next({
//             name: 'home'
//         })
//     }
//
//     return next()
// }
// Import the Pinia store
import { useAuthStore } from '../../store/authStore.js'

export default function guest({ to, from, next }) {
    // Access the auth store's state
    const auth = useAuthStore()

    // Access the isAuthenticated state directly
    const isLogged = auth.authenticated

    if (isLogged) {
        return next({
            name: 'home'
        })
    }

    return next()
}
