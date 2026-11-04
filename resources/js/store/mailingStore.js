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

        // What a mailing can be filled in from. `sourceCategories` maps
        // a category key to the kind of record that fills it in;
        // `sourceTypes` says which placeholders each kind fills.
        sourceCategories: {},
        sourceTypes: {},
        sourceOptions: [],
        loadingSources: false,
        filling: false,

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

        /**
         * What a mailing of this kind can be filled in from, or null
         * when there is nothing on the site to fill it from - a password
         * reset is about the member, not about anything else.
         */
        sourceForCategory: (state) => (categoryId) => {
            const category = state.categories.find((row) => row.id === categoryId);

            return category ? (state.sourceCategories[category.key] || null) : null;
        },

        /**
         * The placeholders one kind of record fills in.
         */
        sourceVariables: (state) => (type) => state.sourceTypes[type]?.variables || [],

        /**
         * What a set of declared placeholders says a template is about.
         *
         * The closest match wins rather than the first one tried:
         * `points` belongs to an achievement and to a rank alike, and a
         * rank template asking for both is about the rank.
         */
        sourceForVariables: (state) => (names) => {
            let best = null;
            let score = 0;

            for (const [type, definition] of Object.entries(state.sourceTypes)) {
                const overlap = (names || [])
                    .filter((name) => (definition.variables || []).includes(name)).length;

                if (overlap > score) {
                    best = type;
                    score = overlap;
                }
            }

            return best;
        },
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
                this.sourceCategories = data.sources?.categories || {};
                this.sourceTypes = data.sources?.types || {};
            } finally {
                this.loadingOptions = false;
            }
        },

        /**
         * The records of one kind to choose from, narrowed by what has
         * been typed. Kept on the store rather than returned, for the
         * same reason the head count is: the next keystroke would race
         * with a local variable.
         */
        async fetchSourceOptions(type, search = null) {
            this.loadingSources = true;
            try {
                const { data } = await axios.get(`/api/admin/mailings/sources/${type}`, {
                    params: search ? { search } : {},
                });
                this.sourceOptions = data.options || [];
                return this.sourceOptions;
            } finally {
                this.loadingSources = false;
            }
        },

        /**
         * One record's values put into the wording being written.
         *
         * The server does the substituting, so the date reads the way
         * the recipient's language writes it and only the source's own
         * placeholders are touched - {{ name }} has to survive until the
         * mail is addressed to somebody.
         */
        async fillFromSource(payload) {
            this.filling = true;
            try {
                const { data } = await axios.post('/api/admin/mailings/fill', payload);
                return data;
            } finally {
                this.filling = false;
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
                this.sourceCategories = data.sources?.categories || this.sourceCategories;
                this.sourceTypes = data.sources?.types || this.sourceTypes;
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
        async draftFromTemplate(id, locale = null, source = null) {
            const { data } = await axios.post(`/api/admin/mail-templates/${id}/draft`, {
                locale,
                // Already picked something to fill it in from? Then the
                // wording arrives filled, and loading plus filling in is
                // one press rather than two.
                source_type: source?.type || null,
                source_id: source?.id || null,
            });
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
