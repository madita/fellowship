import { useI18n } from 'vue-i18n';

/**
 * How an activity entry reads as a sentence.
 *
 * One place, because there were three: ForumActivityFeed switched on the
 * event with i18n, ForumWidget switched on the same four events with
 * hard-coded English, and the admin dashboard showed the raw English
 * `description` column. Adding an event meant remembering all three.
 *
 * An event with no wording yet falls back to `description`, which is the
 * English string the backend stored — readable, just not translated.
 */
export function useActivityText() {
    const { t, te } = useI18n();

    /**
     * Keys live under `activity.events.<event>` and take {title} and
     * {name}, so adding an event is a translation entry and nothing else.
     */
    const activityText = (activity) => {
        if (!activity) return '';

        const key = `activity.events.${activity.event}`;

        if (activity.event && te(key)) {
            return t(key, {
                title: activity.title ?? '',
                name: activity.causer?.username ?? '',
                // The extras a few events carry
                status: activity.properties?.status ?? '',
                assignee: activity.properties?.assignee ?? '',
                forum: activity.properties?.forum_name ?? '',
            });
        }

        return activity.description ?? '';
    };

    const activityIcon = (activity) => {
        const key = `activity.icons.${activity?.log}`;

        return te(key) ? t(key) : 'mdi-pulse';
    };

    return { activityText, activityIcon };
}
