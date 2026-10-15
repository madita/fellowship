import { i18n } from '@/plugins/vue-i18n.js';

/**
 * The step in a breadcrumb trail that names a section of the site.
 *
 * Sixty page headers show a trail, and most of them start with the
 * section the page belongs to: a forum thread, a forum category, the new
 * thread form and the search results all begin "Forum". Spelling that
 * step out in each of them is how the wording, the link and the icon
 * drift apart, so it lives here once:
 *
 *   breadcrumbs() {
 *       return [section('wiki'), this.$t('wiki.categories')];
 *   }
 *
 * A template that passes its trail straight to the page header uses
 * $crumb instead, the same function registered globally in app.js, so a
 * two-step trail needs no script at all:
 *
 *   :breadcrumbs="[$crumb('gallery'), album.name]"
 *
 * The first step of every trail — the dashboard or the landing page — is
 * not in here: Breadcrumbs.vue adds it, because which page it is is the
 * admin's decision rather than the page's.
 */
const SECTIONS = {
    admin: { title: 'admin.dashboard.title', to: { name: 'admin-dashboard' }, icon: 'mdi-shield-account-outline' },
    settings: { title: 'settings.overview.settings', to: { name: 'admin-settings' }, icon: 'mdi-cog-outline' },
    forum: { title: 'forum.title', to: { name: 'forum-index' }, icon: 'mdi-forum-outline' },
    wiki: { title: 'wiki.title', to: { name: 'wiki-index' }, icon: 'mdi-book-open-page-variant' },
    wikiCategories: { title: 'wiki.categories', to: { name: 'wiki-category' }, icon: 'mdi-folder-multiple-outline' },
    gallery: { title: 'gallery.title', to: { name: 'gallery-index' }, icon: 'mdi-image-multiple-outline' },
    events: { title: 'events.title', to: { name: 'events' }, icon: 'mdi-calendar' },
    tickets: { title: 'tickets.title', to: { name: 'my-tickets' }, icon: 'mdi-ticket-outline' },
    feedback: { title: 'feedback.title', to: { name: 'feedback' }, icon: 'mdi-bug-outline' },
    members: { title: 'usersPage.title', to: { name: 'users-list' }, icon: 'mdi-account-group-outline' },
    blog: { title: 'blog.title', to: { name: 'blog' }, icon: 'mdi-post-outline' },
    timeline: { title: 'timeline.title', to: { name: 'timeline' }, icon: 'mdi-timeline-text-outline' },
    chat: { title: 'chat.title', to: { name: 'chat' }, icon: 'mdi-message-text-outline' },
    conversations: { title: 'conversation.messages', to: { name: 'conversations' }, icon: 'mdi-email-outline' },
    irc: { title: 'irc.client.title', to: { name: 'irc-client' }, icon: 'mdi-chat-processing-outline' },
    sandbox: { title: 'sandbox.title', to: { name: 'sandbox.index' }, icon: 'mdi-file-document-edit-outline' },
    achievements: { title: 'achievements.title', to: { name: 'achievements' }, icon: 'mdi-trophy-outline' },
};

/**
 * One section as a crumb. An unknown name returns null, which the trail
 * drops, rather than a step reading "undefined".
 */
export const section = (name) => {
    const found = SECTIONS[name];

    if ( ! found) {
        return null;
    }

    return {
        title: i18n.global.t(found.title),
        to: found.to,
        icon: found.icon,
    };
};

export const sectionNames = () => Object.keys(SECTIONS);

export default section;
