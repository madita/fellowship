<template>
    <v-dialog v-model="open" max-width="900" scrollable persistent>
        <v-card rounded="lg">
            <v-card-title class="d-flex align-center">
                <v-icon class="mr-2" color="primary">mdi-file-document-edit-outline</v-icon>
                {{ template?.id ? $t('mailings.templates.editTitle') : $t('mailings.templates.newTitle') }}
                <v-chip v-if="template?.is_system" class="ml-3" size="small" variant="tonal" color="info">
                    {{ $t('mailings.templates.systemBadge') }}
                </v-chip>
                <v-spacer />
                <v-btn icon="mdi-close" variant="text" :aria-label="$t('common.close')" @click="open = false" />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <!-- A system template is one the code sends by key. Said
                     plainly, because the key field being read-only is
                     otherwise just a locked box. -->
                <v-alert
                    v-if="template?.is_system"
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                >
                    {{ $t('mailings.templates.systemHint', { key: template.key }) }}
                </v-alert>

                <v-row dense>
                    <v-col cols="12" md="5">
                        <v-text-field
                            v-model="form.key"
                            :label="$t('mailings.templates.key')"
                            :hint="$t('mailings.templates.keyHint')"
                            persistent-hint
                            variant="outlined"
                            density="comfortable"
                            :disabled="template?.is_system"
                        />
                    </v-col>
                    <v-col cols="12" md="5">
                        <v-select
                            v-model="form.mail_category_id"
                            :items="store.categories"
                            item-title="name"
                            item-value="id"
                            :label="$t('mailings.fields.category')"
                            :error-messages="errors.mail_category_id"
                            variant="outlined"
                            density="comfortable"
                        />
                    </v-col>
                    <v-col cols="12" md="2" class="d-flex align-center">
                        <v-switch
                            v-model="form.is_enabled"
                            color="primary"
                            density="compact"
                            hide-details
                            :label="$t('mailings.templates.enabled')"
                        />
                    </v-col>
                </v-row>

                <div v-if="!form.is_enabled" class="text-caption text-warning mb-3">
                    {{ $t('mailings.templates.disabledHint') }}
                </div>

                <!-- One tab per language. The fallback one has to be
                     filled in; the rest may be left empty, and an empty
                     one is cleared rather than kept. -->
                <v-tabs v-model="locale" density="compact" class="mb-2">
                    <v-tab v-for="code in store.locales" :key="code" :value="code">
                        {{ code.toUpperCase() }}
                        <v-icon
                            v-if="code === store.defaultLocale"
                            size="14"
                            class="ml-1"
                            :title="$t('mailings.templates.requiredLocale')"
                        >mdi-asterisk</v-icon>
                        <v-icon v-else-if="hasWording(code)" size="14" class="ml-1" color="success">mdi-check</v-icon>
                    </v-tab>
                </v-tabs>

                <v-window v-model="locale">
                    <v-window-item v-for="code in store.locales" :key="code" :value="code">
                        <v-text-field
                            v-model="form.translations[code].subject"
                            :label="$t('mailings.fields.subject')"
                            :error-messages="errors[`translations.${code}.subject`]"
                            variant="outlined"
                            density="comfortable"
                            counter="255"
                            class="mt-2"
                        />

                        <div class="text-caption text-medium-emphasis mb-1">{{ $t('mailings.fields.body') }}</div>
                        <simple-editor
                            v-model="form.translations[code].body"
                            :placeholder="$t('mailings.fields.bodyPlaceholder')"
                            :limit="20000"
                            min-height="180px"
                        />
                        <div v-if="errors[`translations.${code}.body`]" class="text-caption text-error mt-1">
                            {{ errors[`translations.${code}.body`] }}
                        </div>
                    </v-window-item>
                </v-window>

                <!-- The placeholders this template is filled with. Click
                     one to copy it rather than retyping the braces. -->
                <div class="d-flex flex-wrap align-center ga-1 mt-3">
                    <span class="text-caption text-medium-emphasis mr-1">{{ $t('mailings.fields.placeholders') }}</span>
                    <v-chip
                        v-for="name in allVariables"
                        :key="name"
                        size="x-small"
                        variant="tonal"
                        :color="store.baseVariables.includes(name) ? undefined : 'primary'"
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
                        />
                    </v-col>
                    <v-col cols="12" md="8">
                        <v-text-field
                            v-model="form.action_url"
                            :label="$t('mailings.fields.actionUrl')"
                            :hint="$t('mailings.templates.actionUrlHint')"
                            persistent-hint
                            variant="outlined"
                            density="comfortable"
                        />
                    </v-col>
                </v-row>

                <!-- Rendered rather than described: a preview is how the
                     placeholder typed as {{ usrname }} gets caught. -->
                <v-expand-transition>
                    <v-card v-if="preview" variant="outlined" class="mt-4">
                        <v-card-title class="text-subtitle-2">{{ preview.subject }}</v-card-title>
                        <v-divider />
                        <v-card-text>
                            <div class="mail-preview" v-html="preview.body"></div>
                            <v-alert
                                v-if="preview.unknown?.length"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                class="mt-3"
                            >
                                {{ $t('mailings.templates.unknownPlaceholders', { names: preview.unknown.join(', ') }) }}
                            </v-alert>
                        </v-card-text>
                    </v-card>
                </v-expand-transition>
            </v-card-text>

            <v-divider />

            <v-card-actions class="flex-wrap ga-1">
                <v-btn
                    v-if="template?.id"
                    variant="text"
                    prepend-icon="mdi-eye-outline"
                    :loading="previewing"
                    :disabled="previewing"
                    @click="loadPreview"
                >
                    {{ $t('mailings.actions.preview') }}
                </v-btn>
                <v-spacer />
                <v-btn variant="text" :disabled="store.saving" @click="open = false">{{ $t('common.cancel') }}</v-btn>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-content-save-outline"
                    :loading="store.saving"
                    :disabled="store.saving"
                    @click="save"
                >
                    {{ $t('common.save') }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import { useMailingStore } from '@/store/mailingStore.js';
import SimpleEditor from '@/components/common/tiptap/SimpleEditor.vue';

/**
 * The wording of one template, in every language the site is translated
 * into.
 *
 * A language is written in or it is not: a subject with no body would go
 * out as an empty mail, so half-filled tabs are dropped on save rather
 * than stored. The one marked with an asterisk is the fallback and has to
 * be filled in, because it is what a member whose own language is missing
 * reads.
 */
export default {
    name: 'MailTemplateDialog',
    components: { SimpleEditor },
    props: {
        modelValue: { type: Boolean, default: false },
        template: { type: Object, default: null },
    },
    emits: ['update:modelValue', 'saved'],
    setup() {
        return { store: useMailingStore() };
    },
    data() {
        return {
            form: { key: '', mail_category_id: null, is_enabled: true, action_label: '', action_url: '', translations: {} },
            locale: null,
            errors: {},
            preview: null,
            previewing: false,
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
         * What may be written in this one: the names every mail gets,
         * plus whatever this template is handed on top.
         */
        allVariables() {
            const own = this.template?.variables || [];
            return [...this.store.baseVariables, ...own.filter((name) => !this.store.baseVariables.includes(name))];
        },
    },
    watch: {
        modelValue(opened) {
            if (opened) this.reset();
        },
    },
    mounted() {
        if (this.modelValue) this.reset();
    },
    methods: {
        reset() {
            this.errors = {};
            this.preview = null;
            this.locale = this.store.defaultLocale;

            const translations = {};

            for (const code of this.store.locales) {
                translations[code] = {
                    subject: this.template?.translations?.[code]?.subject || '',
                    body: this.template?.translations?.[code]?.body || '',
                };
            }

            this.form = {
                key: this.template?.key || '',
                mail_category_id: this.template?.mail_category_id ?? this.store.categories[0]?.id ?? null,
                is_enabled: this.template ? this.template.is_enabled : true,
                action_label: this.template?.action_label || '',
                action_url: this.template?.action_url || '',
                translations,
            };
        },

        hasWording(code) {
            const wording = this.form.translations[code];
            return !!(wording?.subject && wording.subject.trim());
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

        async save() {
            this.errors = {};
            try {
                const result = await this.store.saveTemplate({
                    id: this.template?.id,
                    key: this.form.key,
                    mail_category_id: this.form.mail_category_id,
                    is_enabled: this.form.is_enabled,
                    action_label: this.form.action_label || null,
                    action_url: this.form.action_url || null,
                    variables: this.template?.variables || [],
                    translations: this.form.translations,
                });

                this.$emit('saved', result.data);
                this.$dialog.success(result.message);
                this.open = false;
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = Object.fromEntries(
                        Object.entries(error.response.data.errors || {})
                            .map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages])
                    );
                }
                this.$dialog.requestError(error);
            }
        },

        async loadPreview() {
            this.previewing = true;
            try {
                // Filled with the placeholder's own name, so the preview
                // shows where each one lands instead of a row of blanks
                const variables = Object.fromEntries(
                    (this.template?.variables || []).map((name) => [name, `[${name}]`])
                );

                this.preview = await this.store.previewTemplate(this.template.id, { locale: this.locale, variables });
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.previewing = false;
            }
        },
    },
};
</script>

<style scoped>
.mail-preview :deep(p) {
    margin-bottom: 0.75rem;
}

.mail-preview :deep(a) {
    color: rgb(var(--v-theme-primary));
}
</style>
