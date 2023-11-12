import { useAuthStore } from "@/store/authStore.js";

export default function auth({ to, from, next }) {
    const authStore = useAuthStore();

    if (!authStore.isLoggedIn) {
        return next({
            name: "auth-signin",
        });
    }

    return next();
}
