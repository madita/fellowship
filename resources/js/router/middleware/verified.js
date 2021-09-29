export default function verified ({ to, from, store, next }){
    const user = store.getters['auth/user'];

    if(!user.email_verified_at){
        return next({
            name: 'auth-verify-email'
        })
    }

    return next()
}
