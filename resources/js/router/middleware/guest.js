import store from '../../store'

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
export default function guest ({ to, from, next }){
    const isLogged = store.getters['auth/authenticated'];

    if(isLogged){
        return next({
            name: 'home'
        })
    }

    return next()
}
