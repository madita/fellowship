export default function auth ({ to, from, store, next }){
    const isLogged = store.getters['auth/authenticated'];

    if(!isLogged){
        return next({
            name: 'auth-signin'
        })
    }

    return next()
}
