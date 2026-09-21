/**
 * How a notification reads, wherever it is shown: the toolbar bell, the
 * dashboard widget and the notifications page. Every notification type the
 * backend sends is described here once.
 *
 * `t` is the translate function of the calling component ($t or useI18n).
 */

const SANDBOX_ICONS = {
    sandbox_shared: 'mdi-share-variant',
    sandbox_removed: 'mdi-account-remove',
    sandbox_comment: 'mdi-comment-text-outline',
    sandbox_reply: 'mdi-reply',
    sandbox_resolved: 'mdi-check-circle-outline',
    sandbox_invite_accepted: 'mdi-account-check',
};

const FORUM_ICONS = {
    forum_reply: 'mdi-forum-outline',
    forum_mention: 'mdi-at',
};

const TICKET_ICONS = {
    ticket_mention: 'mdi-at',
    ticket_comment: 'mdi-comment-text-outline',
    ticket_status: 'mdi-progress-check',
};

/**
 * One line saying what happened.
 */
export function notificationSubject(data = {}, t) {
    const type = data.type || '';

    if (type === 'status_mention' || type === 'status_comment_mention') {
        return t(type === 'status_mention' ? 'notifications.statusMention' : 'notifications.statusCommentMention', { name: data.mentioned_by });
    }
    if (type === 'mention') {
        return t('notifications.mention', { name: data.mentioned_by, title: data.subject });
    }
    if (type === 'ticket_mention') {
        return t('notifications.ticketMention', { name: data.mentioned_by, title: data.ticket_title });
    }
    if (type === 'ticket_comment') {
        return t('notifications.ticketComment', { name: data.comment_author, title: data.ticket_title });
    }
    if (type === 'ticket_status') {
        return t('notifications.ticketStatus', { title: data.ticket_title, status: t(`tickets.status.${data.status}`) });
    }

    if (type === 'achievement_earned') {
        return data.awarded_by
            ? t('notifications.achievementAwarded', { name: data.awarded_by, title: data.achievement_name })
            : t('notifications.achievementEarned', { title: data.achievement_name });
    }

    return data.subject || data.thread_title || data.sandbox_title || t('notifications.title');
}

export function notificationIcon(data = {}) {
    const type = data.type || '';

    if (type.startsWith('sandbox_')) return SANDBOX_ICONS[type] || 'mdi-file-document-edit-outline';
    if (type.startsWith('forum_')) return FORUM_ICONS[type] || 'mdi-forum';
    if (type.startsWith('status_') || type === 'mention') return 'mdi-at';
    if (type.startsWith('ticket_')) return TICKET_ICONS[type] || 'mdi-ticket-outline';

    // The achievement carries the look an admin gave it
    if (type === 'achievement_earned') return data.icon || 'mdi-trophy-outline';

    return 'mdi-bell-outline';
}

const SANDBOX_COLORS = {
    sandbox_shared: 'primary',
    sandbox_removed: 'error',
    sandbox_comment: 'info',
    sandbox_reply: 'info',
    sandbox_resolved: 'success',
    sandbox_invite_accepted: 'success',
};

export function notificationColor(data = {}) {
    const type = data.type || '';

    if (type.startsWith('sandbox_')) return SANDBOX_COLORS[type] || 'primary';
    if (type.startsWith('forum_')) return 'warning';
    if (type === 'achievement_earned') return data.color || 'amber';

    return 'primary';
}

/**
 * Where the notification leads; forum notifications carry the thread address.
 */
export function notificationUrl(data = {}) {
    return data.url || data.thread_url || null;
}

/**
 * The bit of the content the notification is about, as plain text.
 */
export function notificationExcerpt(data = {}) {
    return data.excerpt || data.post_excerpt || data.note || null;
}
