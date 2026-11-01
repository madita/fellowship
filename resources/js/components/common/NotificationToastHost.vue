<template>
    <!-- Nothing is rendered until something arrives, so the stack never
         sits in front of the page catching clicks -->
    <transition-group
        v-if="toasts.length"
        name="toast"
        tag="div"
        class="notification-toasts"
        :aria-label="$t('notifications.title')"
        role="status"
        aria-live="polite"
    >
        <v-card
            v-for="toast in toasts"
            :key="toast.id"
            class="notification-toast"
            :class="{ 'notification-toast--link': urlOf(toast) }"
            rounded="lg"
            elevation="8"
            border
            @click="open(toast)"
        >
            <div class="d-flex align-start pa-3 ga-3">
                <v-avatar :color="colorOf(toast)" size="36">
                    <v-icon size="18" color="white">{{ iconOf(toast) }}</v-icon>
                </v-avatar>

                <div class="flex-grow-1 overflow-hidden">
                    <div class="text-body-2 font-weight-medium">{{ subjectOf(toast) }}</div>
                    <div
                        v-if="excerptOf(toast)"
                        class="text-caption text-medium-emphasis notification-toast__excerpt"
                    >
                        {{ excerptOf(toast) }}
                    </div>
                </div>

                <v-btn
                    icon
                    variant="text"
                    size="x-small"
                    color="medium-emphasis"
                    :aria-label="$t('notifications.dismiss')"
                    @click.stop="dismiss(toast.id)"
                >
                    <v-icon size="16">mdi-close</v-icon>
                </v-btn>
            </div>
        </v-card>
    </transition-group>
</template>

<script>
import { computed, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useUserStore } from '@/store/userStore.js';
import { useNotificationToastStore } from '@/store/notificationToastStore.js';
import {
    startNotificationChannel,
    stopNotificationChannel,
    onNotification,
} from '@/composables/useNotificationChannel.js';
import {
    notificationSubject,
    notificationIcon,
    notificationColor,
    notificationUrl,
    notificationExcerpt,
} from '@/utils/notifications.js';

/**
 * Says out loud what has just happened, and owns the notification channel
 * the rest of the app listens to.
 *
 * Mounted once in App.vue, next to DialogHost, so a notification arriving
 * while the member is deep in the wiki or on a page with no toolbar is
 * still seen. The toasts themselves can be turned off in the settings
 * drawer; the channel stays open either way, because the bell and the
 * sandbox widget are fed from it.
 */
export default {
    name: 'NotificationToastHost',

    setup() {
        const userStore = useUserStore();
        const toastStore = useNotificationToastStore();
        const router = useRouter();
        const { t } = useI18n();

        const toasts = computed(() => toastStore.toasts);

        // The socket payload carries the notification data itself
        const subjectOf = (toast) => notificationSubject(toast.data, t);
        const iconOf = (toast) => notificationIcon(toast.data);
        const colorOf = (toast) => notificationColor(toast.data);
        const urlOf = (toast) => notificationUrl(toast.data);

        // The announcement's body is HTML; a toast shows plain text only,
        // and the full thing is one click away on the notifications page
        const excerptOf = (toast) => notificationExcerpt(toast.data);

        const dismiss = (id) => toastStore.dismiss(id);

        const open = (toast) => {
            const url = urlOf(toast);
            toastStore.dismiss(toast.id);

            // Left unread on purpose: the toast is a heads-up, and the
            // bell is where a notification is dealt with.
            if (url) router.push(url);
        };

        onNotification((payload) => {
            toastStore.show(payload);
        });

        // Follows the member: opened on sign-in, moved on account switch,
        // closed on sign-out.
        watch(
            () => userStore.user?.id,
            (id) => {
                if (id) {
                    startNotificationChannel(id);
                } else {
                    stopNotificationChannel();
                    toastStore.clear();
                }
            },
            { immediate: true }
        );

        // Whatever the record says wins over the mirrored answer, once it
        // has arrived.
        watch(
            () => userStore.user?.notification_toasts,
            () => toastStore.syncFromUser(userStore.user),
            { immediate: true }
        );

        onUnmounted(() => {
            stopNotificationChannel();
        });

        return {
            toasts,
            subjectOf,
            iconOf,
            colorOf,
            urlOf,
            excerptOf,
            dismiss,
            open,
        };
    },
};
</script>

<style lang="scss" scoped>
.notification-toasts {
    position: fixed;
    // Clear of the app bar, and of nothing in particular on the layouts
    // that have none
    top: 76px;
    right: 16px;
    z-index: 2100;
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: min(380px, calc(100vw - 32px));
    pointer-events: none;
}

// Opaque on purpose, the way the drawers and menus are: the app-wide
// surface opacity would otherwise let the page read through a toast that
// is sitting on top of it (see utils/themeInit.js)
.notification-toasts .notification-toast {
    pointer-events: auto;
    background: rgb(var(--v-theme-surface)) !important;
}

.notification-toast--link {
    cursor: pointer;
}

// Two lines of the content it is about, then an ellipsis
.notification-toast__excerpt {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.toast-enter-active,
.toast-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(24px);
}

// The gap left by a dismissed toast closes rather than jumping shut
.toast-move {
    transition: transform 0.25s ease;
}
</style>
