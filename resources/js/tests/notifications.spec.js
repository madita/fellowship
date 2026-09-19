import { describe, it, expect } from 'vitest';
import en from '@/translations/en.js';
import {
    notificationSubject,
    notificationIcon,
    notificationColor,
    notificationUrl,
    notificationExcerpt,
} from '@/utils/notifications.js';

// Stand-in for $t: resolves the key and fills {placeholders}
const t = (key, values = {}) => {
    const message = key.split('.').reduce((o, part) => o?.[part], en);
    if (message === undefined) throw new Error(`missing translation: ${key}`);
    return message.replace(/\{(\w+)\}/g, (_, name) => values[name]);
};

describe('notification display', () => {
    it('reads a mention anywhere in the site', () => {
        const data = { type: 'mention', mentioned_by: 'alice', subject: 'The Fellowship', url: '/wiki/the-fellowship' };

        expect(notificationSubject(data, t)).toBe('alice mentioned you in "The Fellowship"');
        expect(notificationIcon(data)).toBe('mdi-at');
        expect(notificationUrl(data)).toBe('/wiki/the-fellowship');
    });

    it('reads ticket comments and status changes', () => {
        expect(notificationSubject({ type: 'ticket_comment', comment_author: 'boss', ticket_title: 'Login broken' }, t))
            .toBe('boss commented on "Login broken"');
        expect(notificationSubject({ type: 'ticket_status', ticket_title: 'Login broken', status: 'in_progress' }, t))
            .toBe('"Login broken" is now In Progress');
    });

    it('reads timeline mentions', () => {
        expect(notificationSubject({ type: 'status_mention', mentioned_by: 'bob' }, t)).toBe('bob mentioned you in a post');
    });

    it('falls back to the subject an announcement carries', () => {
        expect(notificationSubject({ subject: 'Server maintenance' }, t)).toBe('Server maintenance');
        expect(notificationSubject({}, t)).toBe('Notifications');
    });

    it('uses the thread address of forum notifications', () => {
        const data = { type: 'forum_reply', thread_title: 'Hi', thread_url: '/forum/general/hi', post_excerpt: 'Welcome' };

        expect(notificationUrl(data)).toBe('/forum/general/hi');
        expect(notificationIcon(data)).toBe('mdi-forum-outline');
        expect(notificationColor(data)).toBe('warning');
        expect(notificationExcerpt(data)).toBe('Welcome');
    });
});
