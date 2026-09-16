import { useI18n } from 'vue-i18n'

/**
 * Shared relative-time formatting for sandbox comments and notifications.
 *
 * Returns a short, localized human-readable string ("Just now", "5m ago",
 * "2h ago", "3d ago") for recent dates and falls back to the locale date
 * string for anything a week or older. Previously duplicated as `formatDate`
 * in SandboxComments.vue and `formatTime` in SandboxNotifications.vue.
 *
 * Must be called from within a component's `setup()` so the vue-i18n instance
 * is available and translations stay reactive to locale changes.
 */
export function useRelativeTime() {
    const { t } = useI18n()

    const formatRelativeTime = (dateStr) => {
        if (!dateStr) return ''
        const date = new Date(dateStr)
        const now = new Date()
        const diffMs = now - date
        const diffMins = Math.floor(diffMs / 60000)
        const diffHours = Math.floor(diffMs / 3600000)
        const diffDays = Math.floor(diffMs / 86400000)

        if (diffMins < 1) return t('relativeTime.justNow')
        if (diffMins < 60) return t('relativeTime.minutesShort', { count: diffMins })
        if (diffHours < 24) return t('relativeTime.hoursShort', { count: diffHours })
        if (diffDays < 7) return t('relativeTime.daysShort', { count: diffDays })

        return date.toLocaleDateString()
    }

    return { formatRelativeTime }
}
