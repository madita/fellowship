<template>
    <v-dialog v-model="open" max-width="980" scrollable persistent>
        <v-card rounded="lg">
            <v-card-title class="d-flex align-center">
                <v-icon class="mr-2" color="primary">mdi-email-edit-outline</v-icon>
                {{ mailing?.id ? $t('mailings.composer.editTitle') : $t('mailings.composer.title') }}
                <v-spacer />
                <v-btn icon="mdi-close" variant="text" :aria-label="$t('common.close')" @click="close" />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-row dense>
                    <!-- What kind of mail this is. It decides who may be
                         written to and which unsubscribe link goes in the
                         footer, so it is the first question. -->
                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.mail_category_id"
                            :items="store.sendableCategories"
                            item-title="name"
                            item-value="id"
                            :label="$t('mailings.fields.category')"
                            :error-messages="errors.mail_category_id"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!editable"
                        >
                            <template #item="{ props, item }">
                                <v-list-item v-bind="props" :prepend-icon="item.raw.icon" :subtitle="item.raw.description" />
                            </template>
                        </v-select>
                    </v-col>

                    <!-- Start from the wording of a template, in a chosen
                         language. What arrives is a copy. -->
                    <v-col cols="12" md="4">
                        <v-select
                            v-model="templateId"
                            :items="templateItems"
                            item-title="label"
                            item-value="id"
                            :label="$t('mailings.fields.startFrom')"
                            :hint="$t('mailings.fields.startFromHint')"
                            persistent-hint
                            clearable
                            variant="outlined"
                            density="comfortable"
                            :disabled="!editable"
                        />
                    </v-col>

                    <v-col cols="12" md="2">
                        <v-select
                            v-model="templateLocale"
                            :items="store.locales"
                            :label="$t('mailings.fields.language')"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!editable || !templateId"
                        />
                    </v-col>
                </v-row>

                <v-btn
                    v-if="templateId && editable"
                    class="mb-4"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-content-duplicate"
                    :loading="loadingTemplate"
                    :disabled="loadingTemplate"
                    @click="loadTemplate"
                >
                    {{ $t('mailings.composer.useTemplate') }}
                </v-btn>

                <!-- Fill the wording in from something already on the
                     site. The point is the date: typed from memory it is
                     wrong often enough, and four hundred people get it. -->
                <v-sheet v-if="sourceType && editable" class="mb-4 pa-3" rounded="lg" border>
                    <div class="d-flex align-center mb-2">
                        <v-icon size="18" class="mr-2" color="primary">mdi-database-arrow-right-outline</v-icon>
                        <span class="text-subtitle-2">{{ $t(`mailings.source.title.${sourceType}`) }}</span>
                    </div>

                    <v-row dense align="center">
                        <v-col cols="12" md="8">
                            <v-autocomplete
                                v-model="sourceId"
                                v-model:search="sourceSearch"
                                :items="store.sourceOptions"
                                :loading="store.loadingSources"
                                item-title="label"
                                item-value="id"
                                :label="$t(`mailings.source.pick.${sourceType}`)"
                                :no-data-text="$t('mailings.source.nothing')"
                                hide-details="auto"
                                clearable
                                variant="outlined"
                                density="comfortable"
                            >
                                <template #item="{ props, item }">
                                    <v-list-item
                                        v-bind="props"
                                        :prepend-icon="item.raw.icon"
                                        :subtitle="item.raw.hint"
                                    />
                                </template>
                            </v-autocomplete>
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-btn
                                block
                                variant="tonal"
                                color="primary"
                                prepend-icon="mdi-form-textbox"
                                :loading="store.filling"
                                :disabled="!sourceId || store.filling"
                                @click="fillFromSource"
                            >
                                {{ $t('mailings.source.fill') }}
                            </v-btn>
                        </v-col>
                    </v-row>

                    <!-- What this one fills in. Green once it has. -->
                    <div class="d-flex flex-wrap align-center ga-1 mt-2">
                        <v-chip
                            v-for="name in store.sourceVariables(sourceType)"
                            :key="name"
                            size="x-small"
                            variant="tonal"
                            :color="filledNames.includes(name) ? 'success' : undefined"
                            :title="$t('mailings.fields.copyPlaceholder')"
                            @click="copyPlaceholder(name)"
                        >
                            {{ placeholder(name) }}
                        </v-chip>
                    </div>

                    <!-- Said inline rather than in a dialog: whoever
                         pressed the button is looking straight at this,
                         and a form being filled in is no place for one. -->
                    <div
                        v-if="fillTried"
                        class="text-caption mt-2"
                        :class="filledNames.length ? 'text-success' : 'text-warning'"
                    >
                        {{ filledNames.length
                            ? $t('mailings.source.filled', { label: sourceLabel, names: filledNames.join(', ') })
                            : $t('mailings.source.nonePresent') }}
                    </div>
                </v-sheet>

                <v-text-field
                    v-model="form.subject"
                    :label="$t('mailings.fields.subject')"
                    :error-messages="errors.subject"
                    :hint="$t('mailings.fields.subjectHint')"
                    persistent-hint
                    variant="outlined"
                    density="comfortable"
                    class="mb-4"
                    counter="255"
                    :disabled="!editable"
                />

                <div class="text-caption text-medium-emphasis mb-1">{{ $t('mailings.fields.body') }}</div>
                <simple-editor
                    v-model="form.body"
                    :placeholder="$t('mailings.fields.bodyPlaceholder')"
                    :limit="20000"
                    min-height="200px"
                    :disabled="!editable"
                />
                <div v-if="errors.body" class="text-caption text-error mt-1">{{ errors.body }}</div>

                <!-- The placeholders, as chips to copy rather than a list
                     to remember -->
                <div class="d-flex flex-wrap align-center ga-1 mt-2">
                    <span class="text-caption text-medium-emphasis mr-1">{{ $t('mailings.fields.placeholders') }}</span>
                    <v-chip
                        v-for="name in store.baseVariables"
                        :key="name"
                        size="x-small"
                        variant="tonal"
                        :title="$t('mailings.fields.copyPlaceholder')"
                        @click="copyPlaceholder(name)"
                    >
                        {{ placeholder(name) }}
                    </v-chip>
                </div>

                <v-row dense class="mt-4">
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.action_label"
                            :label="$t('mailings.fields.actionLabel')"
                            variant="outlined"
                            density="comfortable"
                            counter="80"
                            :disabled="!editable"
                        />
                    </v-col>
                    <v-col cols="12" md="8">
                        <v-text-field
                            v-model="form.action_url"
                            :label="$t('mailings.fields.actionUrl')"
                            :error-messages="errors.action_url"
                            :hint="$t('mailings.fields.actionHint')"
                            persistent-hint
                            variant="outlined"
                            density="comfortable"
                            :disabled="!editable"
                        />
                    </v-col>
                </v-row>

                <v-divider class="my-5" />

                <!-- Who it is for -->
                <div class="d-flex align-center mb-3">
                    <v-icon class="mr-2" color="primary">mdi-account-group-outline</v-icon>
                    <span class="text-subtitle-2 font-weight-medium">{{ $t('mailings.audience.title') }}</span>
                </div>

                <v-btn-toggle
                    v-model="form.audience.type"
                    mandatory
                    divided
                    variant="outlined"
                    density="compact"
                    color="primary"
                    class="mb-4 flex-wrap"
                    :disabled="!editable"
                >
                    <v-btn v-for="type in audienceTypes" :key="type.value" :value="type.value" size="small">
                        <v-icon size="18" class="mr-1">{{ type.icon }}</v-icon>
                        {{ type.label }}
                    </v-btn>
                </v-btn-toggle>

                <v-select
                    v-if="form.audience.type === 'roles'"
                    v-model="form.audience.roles"
                    :items="store.roles"
                    item-title="label"
                    item-value="name"
                    :label="$t('mailings.audience.roles')"
                    multiple
                    chips
                    closable-chips
                    variant="outlined"
                    density="comfortable"
                    :disabled="!editable"
                />

                <v-autocomplete
                    v-if="form.audience.type === 'users'"
                    v-model="form.audience.user_ids"
                    v-model:search="userSearch"
                    :items="userResults"
                    :loading="searchingUsers"
                    item-title="label"
                    item-value="id"
                    :label="$t('mailings.audience.users')"
                    :no-data-text="$t('mailings.audience.searchUsers')"
                    multiple
                    chips
                    closable-chips
                    variant="outlined"
                    density="comfortable"
                    :disabled="!editable"
                />

                <v-select
                    v-if="form.audience.type === 'achievement'"
                    v-model="form.audience.achievement_id"
                    :items="store.achievements"
                    item-title="name"
                    item-value="id"
                    :label="$t('mailings.audience.achievement')"
                    variant="outlined"
                    density="comfortable"
                    :disabled="!editable"
                />

                <v-row dense align="center">
                    <v-col cols="12" md="7">
                        <v-select
                            v-model="form.audience.locales"
                            :items="store.locales"
                            :label="$t('mailings.audience.locales')"
                            :hint="$t('mailings.audience.localesHint')"
                            persistent-hint
                            multiple
                            chips
                            clearable
                            variant="outlined"
                            density="comfortable"
                            :disabled="!editable"
                        />
                    </v-col>
                    <v-col cols="12" md="5">
                        <v-switch
                            v-model="form.audience.verified_only"
                            color="primary"
                            density="compact"
                            hide-details
                            :label="$t('mailings.audience.verifiedOnly')"
                            :disabled="!editable"
                        />
                        <div class="text-caption text-medium-emphasis">
                            {{ $t('mailings.audience.verifiedOnlyHint') }}
                        </div>
                    </v-col>
                </v-row>

                <!-- The head count. Two numbers, because the difference
                     between them is the thing worth knowing. -->
                <v-alert
                    class="mt-4"
                    :type="store.estimate && store.estimate.will_receive === 0 ? 'warning' : 'info'"
                    variant="tonal"
                    density="compact"
                >
                    <div v-if="store.estimating" class="d-flex align-center">
                        <v-progress-circular indeterminate size="16" width="2" class="mr-2" />
                        {{ $t('mailings.audience.counting') }}
                    </div>
                    <div v-else-if="store.estimate">
                        {{ $t('mailings.audience.count', {
                            total: store.estimate.total,
                            receiving: store.estimate.will_receive,
                        }) }}
                        <span v-if="store.estimate.opted_out > 0">
                            {{ $t('mailings.audience.optedOut', { count: store.estimate.opted_out }) }}
                        </span>
                    </div>
                    <div v-else>{{ $t('mailings.audience.notCountedYet') }}</div>
                </v-alert>

                <!-- Scheduling. Empty means now. -->
                <v-row dense class="mt-3">
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="scheduledFor"
                            type="datetime-local"
                            :label="$t('mailings.fields.scheduledFor')"
                            :hint="$t('mailings.fields.scheduledForHint')"
                            persistent-hint
                            clearable
                            variant="outlined"
                            density="comfortable"
                            :disabled="!editable"
                        />
                    </v-col>
                </v-row>
            </v-card-text>

            <v-divider />

            <v-card-actions class="flex-wrap ga-1">
                <v-btn
                    variant="text"
                    prepend-icon="mdi-eye-outline"
                    :loading="previewing"
                    :disabled="previewing || !form.id"
                    @click="showPreview"
                >
                    {{ $t('mailings.actions.preview') }}
                </v-btn>
                <v-btn
                    variant="text"
                    prepend-icon="mdi-email-fast-outline"
                    :loading="testing"
                    :disabled="testing || !form.id"
                    @click="sendTest"
                >
                    {{ $t('mailings.actions.sendTest') }}
                </v-btn>

                <v-spacer />

                <v-btn variant="text" :disabled="busy" @click="close">{{ $t('common.cancel') }}</v-btn>
                <v-btn
                    v-if="editable"
                    variant="tonal"
                    prepend-icon="mdi-content-save-outline"
                    :loading="store.saving"
                    :disabled="busy"
                    @click="save"
                >
                    {{ $t('mailings.actions.saveDraft') }}
                </v-btn>
                <v-btn
                    v-if="editable"
                    color="primary"
                    variant="elevated"
                    :prepend-icon="scheduledFor ? 'mdi-clock-outline' : 'mdi-send'"
                    :loading="store.sending"
                    :disabled="busy"
                    @click="confirmAndSend"
                >
                    {{ scheduledFor ? $t('mailings.actions.schedule') : $t('mailings.actions.sendNow') }}
                </v-btn>
            </v-card-actions>
        </v-card>

        <!-- What one member's copy will say, placeholders filled in -->
        <v-dialog v-model="previewOpen" max-width="700" scrollable>
            <v-card rounded="lg">
                <v-card-title class="text-subtitle-1">{{ preview?.subject }}</v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="mail-preview" v-html="preview?.body"></div>
                    <v-btn
                        v-if="preview?.action_label"
                        class="mt-4"
                        color="primary"
                        variant="elevated"
                        :href="preview.action_url"
                        target="_blank"
                        rel="noopener"
                    >
                        {{ preview.action_label }}
                    </v-btn>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="previewOpen = false">{{ $t('common.close') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-dialog>
</template>

<script>
import axios from 'axios';
import { useMailingStore } from '@/store/mailingStore.js';
import SimpleEditor from '@/components/common/tiptap/SimpleEditor.vue';

const COUNT_DEBOUNCE_MS = 400;
const USER_SEARCH_DEBOUNCE_MS = 300;
const SOURCE_SEARCH_DEBOUNCE_MS = 300;

/**
 * Writing a mailing: what it says, who it is for, and when it goes.
 *
 * Saving and sending are two presses on purpose. The draft is saved as
 * often as anybody likes; sending is the press that reaches four hundred
 * people, and it asks first — with the head count in the question, so
 * nobody confirms a number they have not seen.
 *
 * Preview and test need a saved draft, because both render the mailing
 * the server has rather than the form in the browser. That is the point:
 * what they show is what will actually go out.
 */
export default {
    name: 'MailingComposer',
    components: { SimpleEditor },
    props: {
        modelValue: { type: Boolean, default: false },
        // The mailing being edited, or null for a new one
        mailing: { type: Object, default: null },
    },
    emits: ['update:modelValue', 'saved', 'sent'],
    setup() {
        return { store: useMailingStore() };
    },
    data() {
        return {
            form: this.blank(),
            errors: {},
            templateId: null,
            templateLocale: null,
            loadingTemplate: false,
            scheduledFor: '',
            testing: false,
            previewing: false,
            previewOpen: false,
            preview: null,
            countTimer: null,
            userSearch: '',
            userResults: [],
            searchingUsers: false,
            userSearchTimer: null,
            // What the wording is being filled in from
            sourceId: null,
            sourceSearch: '',
            sourceSearchTimer: null,
            sourceLabel: '',
            filledNames: [],
            fillTried: false,
        };
    },
    computed: {
        open: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit('update:modelValue', value);
            },
        },
        /**
         * A mailing that has gone out is a record of what was sent.
         * Everything stays readable; nothing stays editable.
         */
        editable() {
            return !this.mailing?.id || ['draft', 'scheduled'].includes(this.mailing.status);
        },
        busy() {
            return this.store.saving || this.store.sending || this.testing;
        },
        audienceTypes() {
            return [
                { value: 'all', icon: 'mdi-account-multiple', label: this.$t('mailings.audience.types.all') },
                { value: 'roles', icon: 'mdi-shield-account-outline', label: this.$t('mailings.audience.types.roles') },
                { value: 'users', icon: 'mdi-account-search-outline', label: this.$t('mailings.audience.types.users') },
                { value: 'achievement', icon: 'mdi-trophy-outline', label: this.$t('mailings.audience.types.achievement') },
            ];
        },
        templateItems() {
            return this.store.usableTemplates.map((template) => ({
                id: template.id,
                label: `${template.category?.name ?? ''} — ${this.templateTitle(template)}`,
            }));
        },
        /**
         * What this mailing can be filled in from, if anything.
         *
         * The template decides it when one is loaded - a template asking
         * for an event's placeholders is about an event, wherever
         * somebody filed it - and otherwise the kind of mail does.
         */
        sourceType() {
            const template = this.form.mail_template_id
                ? this.store.templates.find((row) => row.id === this.form.mail_template_id)
                : null;

            return this.store.sourceForVariables(template?.variables)
                || this.store.sourceForCategory(this.form.mail_category_id);
        },
        /**
         * What a count depends on. Watched as one value so a change of
         * audience type and a change of category do not fire two
         * requests.
         */
        countKey() {
            return JSON.stringify([this.form.mail_category_id, this.form.audience]);
        },
    },
    watch: {
        modelValue(opened) {
            if (opened) this.reset();
        },
        countKey() {
            this.scheduleCount();
        },
        userSearch(value) {
            clearTimeout(this.userSearchTimer);
            if (!value || value.length < 2) return;
            this.userSearchTimer = setTimeout(() => this.searchUsers(value), USER_SEARCH_DEBOUNCE_MS);
        },
        /**
         * A different kind of thing to fill in from means the one picked
         * no longer applies, and a fresh list to choose from.
         */
        sourceType(type, previous) {
            if (type === previous) return;

            this.sourceId = null;
            this.sourceSearch = '';
            this.filledNames = [];
            this.fillTried = false;
            this.store.sourceOptions = [];

            if (type) this.loadSourceOptions();
        },
        sourceSearch(value) {
            clearTimeout(this.sourceSearchTimer);

            // Clearing the box asks for the list again rather than
            // leaving whatever the last search found
            if (!value) {
                this.sourceSearchTimer = setTimeout(() => this.loadSourceOptions(), SOURCE_SEARCH_DEBOUNCE_MS);
                return;
            }

            if (value.length < 2) return;

            this.sourceSearchTimer = setTimeout(() => this.loadSourceOptions(value), SOURCE_SEARCH_DEBOUNCE_MS);
        },
    },
    mounted() {
        if (this.modelValue) this.reset();
    },
    beforeUnmount() {
        clearTimeout(this.countTimer);
        clearTimeout(this.userSearchTimer);
        clearTimeout(this.sourceSearchTimer);
    },
    methods: {
        blank() {
            return {
                id: null,
                mail_category_id: null,
                mail_template_id: null,
                subject: '',
                body: '',
                action_label: '',
                action_url: '',
                audience: { type: 'all', roles: [], user_ids: [], achievement_id: null, locales: [], verified_only: true },
            };
        },

        async reset() {
            this.errors = {};
            this.preview = null;
            this.previewOpen = false;
            this.templateId = null;
            this.sourceId = null;
            this.sourceSearch = '';
            this.sourceLabel = '';
            this.filledNames = [];
            this.fillTried = false;
            this.templateLocale = this.store.defaultLocale;
            this.scheduledFor = '';
            this.store.estimate = null;

            if (this.mailing?.id) {
                this.form = {
                    id: this.mailing.id,
                    mail_category_id: this.mailing.mail_category_id,
                    mail_template_id: this.mailing.mail_template_id,
                    subject: this.mailing.subject || '',
                    body: this.mailing.body || '',
                    action_label: this.mailing.action_label || '',
                    action_url: this.mailing.action_url || '',
                    audience: { ...this.blank().audience, ...(this.mailing.audience || {}) },
                };
                this.scheduledFor = this.toLocalInput(this.mailing.scheduled_for);
            } else {
                this.form = this.blank();
                this.form.mail_category_id = this.store.sendableCategories[0]?.id ?? null;
            }

            await Promise.all([
                this.store.roles.length ? Promise.resolve() : this.store.fetchOptions(),
                this.store.templates.length ? Promise.resolve() : this.store.fetchTemplates(),
            ]).catch((error) => this.$dialog.requestError(error));

            if (!this.templateLocale) this.templateLocale = this.store.defaultLocale;

            this.scheduleCount();
        },

        templateTitle(template) {
            const locale = this.templateLocale || this.store.defaultLocale;
            return template.translations?.[locale]?.subject
                || template.translations?.[this.store.defaultLocale]?.subject
                || template.key;
        },

        scheduleCount() {
            clearTimeout(this.countTimer);

            if (!this.form.mail_category_id) return;

            this.countTimer = setTimeout(() => {
                this.store
                    .countAudience(this.form.mail_category_id, this.form.audience)
                    // A head count that fails is not worth a dialog in
                    // the middle of typing — the alert simply says nothing
                    .catch(() => { this.store.estimate = null; });
            }, COUNT_DEBOUNCE_MS);
        },

        async searchUsers(query) {
            this.searchingUsers = true;
            try {
                const { data } = await axios.post('/api/users/search', { query });
                // Whoever is already picked has to stay in the item list,
                // or the chips lose their labels on the next search
                const picked = this.userResults.filter((user) => this.form.audience.user_ids.includes(user.id));
                const found = (data || []).map((user) => ({
                    id: user.id,
                    label: user.name ? `${user.name} (${user.username})` : user.username,
                }));

                this.userResults = [
                    ...picked.filter((user) => !found.some((row) => row.id === user.id)),
                    ...found,
                ];
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.searchingUsers = false;
            }
        },

        async loadTemplate() {
            if (!this.templateId || this.loadingTemplate) return;
            this.loadingTemplate = true;
            try {
                const draft = await this.store.draftFromTemplate(
                    this.templateId,
                    this.templateLocale,
                    // Something already picked comes across filled in,
                    // so this is one press instead of two
                    this.sourceId && this.sourceType ? { type: this.sourceType, id: this.sourceId } : null
                );

                this.form.mail_category_id = draft.mail_category_id;
                this.form.mail_template_id = draft.mail_template_id;
                this.form.subject = draft.subject;
                this.form.body = draft.body;
                this.form.action_label = draft.action_label || '';
                this.form.action_url = draft.action_url || '';

                if (draft.filled) {
                    this.filledNames = draft.filled;
                    this.sourceLabel = draft.source?.label || '';
                    this.fillTried = true;
                }
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.loadingTemplate = false;
            }
        },

        async loadSourceOptions(search = null) {
            if (!this.sourceType) return;

            try {
                await this.store.fetchSourceOptions(this.sourceType, search);
            } catch (error) {
                this.$dialog.requestError(error);
            }
        },

        /**
         * Put the picked record's values into the wording.
         *
         * What comes back is the wording with the source's placeholders
         * replaced and everybody else's left standing, so this can be
         * pressed again after a different event is picked.
         */
        async fillFromSource() {
            if (!this.sourceId || !this.sourceType) return;

            try {
                const result = await this.store.fillFromSource({
                    source_type: this.sourceType,
                    source_id: this.sourceId,
                    locale: this.templateLocale || this.store.defaultLocale,
                    subject: this.form.subject,
                    body: this.form.body,
                    action_label: this.form.action_label || null,
                    action_url: this.form.action_url || null,
                });

                this.form.subject = result.subject;
                this.form.body = result.body;
                this.form.action_label = result.action_label || '';
                this.form.action_url = result.action_url || '';
                this.filledNames = result.filled || [];
                this.sourceLabel = result.source?.label || '';
                this.fillTried = true;
            } catch (error) {
                this.$dialog.requestError(error);
            }
        },

        /**
         * A placeholder as it is written in a body.
         *
         * Built here rather than in the template: a literal pair of
         * braces inside an interpolation closes it early, and the
         * compiler refuses the file.
         */
        placeholder(name) {
            return `{{ ${name} }}`;
        },

        copyPlaceholder(name) {
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(this.placeholder(name)).catch(() => {});
            }
        },

        payload() {
            return {
                ...this.form,
                action_label: this.form.action_label || null,
                action_url: this.form.action_url || null,
            };
        },

        async save() {
            this.errors = {};
            try {
                const result = await this.store.saveMailing(this.payload());
                this.form.id = result.mailing.id;
                this.$emit('saved', result.mailing);
                this.$dialog.success(result.message);
                return result.mailing;
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = this.flatten(error.response.data.errors || {});
                }
                this.$dialog.requestError(error);
                return null;
            }
        },

        /**
         * Save, then ask, then send. Asking with the number in the
         * question is the whole point of the confirmation — "send to 412
         * members?" is a decision, "are you sure?" is not.
         */
        async confirmAndSend() {
            const mailing = await this.save();
            if (!mailing) return;

            const count = this.store.estimate?.will_receive ?? null;

            const ok = await this.$dialog.confirm({
                title: this.scheduledFor
                    ? this.$t('mailings.confirm.scheduleTitle')
                    : this.$t('mailings.confirm.sendTitle'),
                content: this.scheduledFor
                    ? this.$t('mailings.confirm.schedule', { count: count ?? '?', when: this.scheduledFor })
                    : this.$t('mailings.confirm.send', { count: count ?? '?' }),
                confirmationText: this.scheduledFor
                    ? this.$t('mailings.actions.schedule')
                    : this.$t('mailings.actions.sendNow'),
                color: 'primary',
            });

            if (!ok) return;

            try {
                const result = await this.store.send(
                    mailing.id,
                    this.scheduledFor ? new Date(this.scheduledFor).toISOString() : null
                );
                this.$emit('sent', result.mailing);
                this.$dialog.success(result.message);
                this.open = false;
            } catch (error) {
                this.$dialog.requestError(error);
            }
        },

        async sendTest() {
            const mailing = this.form.id ? { id: this.form.id } : await this.save();
            if (!mailing) return;

            this.testing = true;
            try {
                const result = await this.store.sendTest(mailing.id);
                this.$dialog.success(result.message);
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.testing = false;
            }
        },

        async showPreview() {
            if (!this.form.id) return;
            this.previewing = true;
            try {
                this.preview = await this.store.preview(this.form.id);
                this.previewOpen = true;
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.previewing = false;
            }
        },

        close() {
            this.open = false;
        },

        /**
         * Laravel answers with a list of messages per field; a text field
         * wants one string.
         */
        flatten(errors) {
            return Object.fromEntries(
                Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages])
            );
        },

        /**
         * A datetime-local input wants `YYYY-MM-DDTHH:mm` in local time,
         * which is not what an ISO string from the server is.
         */
        toLocalInput(value) {
            if (!value) return '';

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return '';

            const pad = (n) => String(n).padStart(2, '0');

            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        },
    },
};
</script>

<style scoped>
/* The preview shows a mail body, which is plain HTML with no classes of
   ours on it — so the few tags it can contain are styled here. */
.mail-preview :deep(p) {
    margin-bottom: 0.75rem;
}

.mail-preview :deep(a) {
    color: rgb(var(--v-theme-primary));
}

.mail-preview :deep(ul),
.mail-preview :deep(ol) {
    padding-left: 1.5rem;
    margin-bottom: 0.75rem;
}
</style>
