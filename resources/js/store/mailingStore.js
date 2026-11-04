import { defineStore } from 'pinia';
import axios from 'axios';
import { debug } from '@/utils/debug.js';

const log = debug.module('MailingStore');

/**
 * The admin side of the mailing system: what has been sent, the wording
 * of the mails the site sends by itself, and the kinds of mail a member
 * can say no to.
 *
 * A member's own switches are not here — those live with the account page
 * that shows them, because nothing else needs them.
 *
 * Every action rethrows. The pages show failures as a dialog (see
 * $dialog.requestError), so swallowing them here would leave somebody
 * looking at a form that quietly did nothing.
 */
export const useMailingStore = defineStore({
    id: 'mailing',

    state: () => ({
        mailings: [],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
        loadingMailings: false,

        templates: [],
        loadingTemplates: false,

        categories: [],
        loadingCategories: false,

        // Which languages a template may be written in, and the one that
        // has to be filled in — read from the server so adding a
        // language needs no change here
        locales: [],
        defaultLocale: 'en',

        // The placeholders every mail can use, whatever it is about
        baseVariables: [],

        // What the audience picker offers: the roles there are, and the
        // achievements whose holders can be written to
        roles: [],
        achievements: [],
        loadingOptions: false,

        // The last head count asked for, as { total, will_receive, opted_out }
        estimate: null,
        estimating: false,

        saving: false,
        sending: false,
    }),

    getters: {
        /**
         * Categories as a picker wants them.
         */
        categoryOptions: (state) => state.categories.map((category) => ({
            value: category.id,
            title: category.name,
            icon: category.icon,
            key: category.key,
        })),

        /**
         * The ones somebody may actually be written to about. A mandatory
         * category is the mail that gets a member back into their
         * account — not a thing to send a newsletter under.
         */
        sendableCategories: (state) => state.categories.filter((category) => !category.is_mandatory),

        templatesByCategory: (state) => (categoryId) =>
            state.templates.filter((template) => template.mail_category_id === categoryId),

        /**
         * A template may be switched off, and an off template is not
         * something to start a mailing from.
         */
        usableTemplates: (state) => state.templates.filter((template) => template.is_enabled),
    },

    actions: {
        async fetchMailings(params = {}) {
            this.loadingMailings = true;
            try {
                const { data } = await axios.get('/api/admin/mailings', { params });
                this.mailings = data.data || [];
                this.meta = {
                    current_page: data.current_page,
                    last_page: data.last_page,
                    per_page: data.per_page,
                    total: data.total,
                };
                return this.mailings;
            } finally {
                this.loadingMailings = false;
            }
        },

        async fetchMailing(id) {
            const { data } = await axios.get(`/api/admin/mailings/${id}`);
            this.replaceMailing(data.mailing);
            return data;
        },

        async fetchRecipients(id, params = {}) {
            const { data } = await axios.get(`/api/admin/mailings/${id}/recipients`, { params });
            return data;
        },

        async saveMailing(payload) {
            this.saving = true;
            try {
                const { data } = payload.id
                    ? await axios.patch(`/api/admin/mailings/${payload.id}`, payload)
                    : await axios.post('/api/admin/mailings', payload);

                this.replaceMailing(data.mailing);
                return data;
            } finally {
                this.saving = false;
            }
        },

        async deleteMailing(id) {
            const { data } = await axios.delete(`/api/admin/mailings/${id}`);
            this.mailings = this.mailings.filter((mailing) => mailing.id !== id);
            return data;
        },

        /**
         * The roles and achievements the audience picker offers. Asked
         * for once when the composer opens.
         */
        async fetchOptions() {
            if (this.loadingOptions) return;
            this.loadingOptions = true;
            try {
                const { data } = await axios.get('/api/admin/mailings/options');
                this.roles = data.roles || [];
                this.achievements = data.achievements || [];
                this.locales = data.locales || this.locales;
                this.defaultLocale = data.default || this.defaultLocale;
            } finally {
                this.loadingOptions = false;
            }
        },

        /**
         * How many people an audience comes to, while it is still being
         * chosen. Called as the form changes, so the result is kept on
         * the store rather than returned into a local variable that the
         * next keystroke would race with.
         */
        async countAudience(categoryId, audience) {
            this.estimating = true;
            try {
                const { data } = await axios.post('/api/admin/mailings/estimate', {
                    mail_category_id: categoryId,
                    audience,
                });
                this.estimate = data;
                return data;
            } finally {
                this.estimating = false;
            }
        },

        async sendTest(id) {
            const { data } = await axios.post(`/api/admin/mailings/${id}/test`);
            return data;
        },

        async preview(id) {
            const { data } = await axios.get(`/api/admin/mailings/${id}/preview`);
            return data;
        },

        /**
         * Send now, or at a time when one is given.
         */
        async send(id, scheduledFor = null) {
            this.sending = true;
            try {
                const { data } = await axios.post(
                    `/api/admin/mailings/${id}/send`,
                    scheduledFor ? { scheduled_for: scheduledFor } : {}
                );
                this.replaceMailing(data.mailing);
                return data;
            } finally {
                this.sending = false;
            }
        },

        async cancel(id) {
            const { data } = await axios.post(`/api/admin/mailings/${id}/cancel`);
            this.replaceMailing(data.mailing);
            return data;
        },

        async fetchTemplates() {
            this.loadingTemplates = true;
            try {
                const { data } = await axios.get('/api/admin/mail-templates');
                this.templates = data.data || [];
                this.locales = data.locales || [];
                this.defaultLocale = data.default || 'en';
                this.baseVariables = data.base_variables || [];
                return this.templates;
            } finally {
                this.loadingTemplates = false;
            }
        },

        async saveTemplate(payload) {
            this.saving = true;
            try {
                const { data } = payload.id
                    ? await axios.patch(`/api/admin/mail-templates/${payload.id}`, payload)
                    : await axios.post('/api/admin/mail-templates', payload);

                const index = this.templates.findIndex((template) => template.id === data.data.id);
                if (index === -1) {
                    this.templates.push(data.data);
                } else {
                    this.templates.splice(index, 1, data.data);
                }

                return data;
            } finally {
                this.saving = false;
            }
        },

        async deleteTemplate(id) {
            const { data } = await axios.delete(`/api/admin/mail-templates/${id}`);
            this.templates = this.templates.filter((template) => template.id !== id);
            return data;
        },

        async previewTemplate(id, { locale = null, variables = {} } = {}) {
            const { data } = await axios.post(`/api/admin/mail-templates/${id}/preview`, { locale, variables });
            return data;
        },

        /**
         * The wording of a template as the starting point for a mailing.
         * What comes back is a copy — editing it never touches the
         * template, and the template changing later never rewrites a
         * mailing that has gone out.
         */
        async draftFromTemplate(id, locale = null) {
            const { data } = await axios.post(`/api/admin/mail-templates/${id}/draft`, { locale });
            return data;
        },

        async fetchCategories() {
            this.loadingCategories = true;
            try {
                const { data } = await axios.get('/api/admin/mail-categories');
                this.categories = data.data || [];
                this.locales = data.locales || this.locales;
                this.defaultLocale = data.default || this.defaultLocale;
                return this.categories;
            } finally {
                this.loadingCategories = false;
            }
        },

        async saveCategory(payload) {
            this.saving = true;
            try {
                const { data } = payload.id
                    ? await axios.patch(`/api/admin/mail-categories/${payload.id}`, payload)
                    : await axios.post('/api/admin/mail-categories', payload);

                const index = this.categories.findIndex((category) => category.id === data.data.id);
                if (index === -1) {
                    this.categories.push(data.data);
                } else {
                    this.categories.splice(index, 1, data.data);
                }

                return data;
            } finally {
                this.saving = false;
            }
        },

        async deleteCategory(id) {
            const { data } = await axios.delete(`/api/admin/mail-categories/${id}`);
            this.categories = this.categories.filter((category) => category.id !== id);
            return data;
        },

        /**
         * Put a mailing back into the list in place, so a send that just
         * finished updates the row somebody is looking at instead of
         * needing the whole page reloaded.
         */
        replaceMailing(mailing) {
            if (!mailing) return;

            const index = this.mailings.findIndex((row) => row.id === mailing.id);

            if (index === -1) {
                this.mailings.unshift(mailing);
            } else {
                // Merged rather than replaced: the list carries the
                // category and author that a save response leaves out
                this.mailings.splice(index, 1, { ...this.mailings[index], ...mailing });
            }

            log.log('mailing updated', mailing.id, mailing.status);
        },
    },
});
