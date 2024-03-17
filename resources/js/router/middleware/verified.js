import { useUserStore } from "@/store/userStore.js";
export default function verified ({ to, from, next }){
    const user = useUserStore();

    if(!user.user.email_verified_at){
        return next({
            name: 'auth-verify-email'
        })
    }

    return next()
}
