export default function guest ({ to, from, store, next }){
    const isLogged = store.getters['auth/authenticated'];

    if(isLogged){
        return next({
            name: 'home'
        })
    }

    return next()
}
