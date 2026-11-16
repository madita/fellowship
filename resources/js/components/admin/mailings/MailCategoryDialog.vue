<template>
    <v-dialog v-model="open" max-width="640" scrollable>
        <v-card rounded="lg">
            <v-card-title class="d-flex align-center">
                <v-icon class="mr-2" color="primary">mdi-shape-outline</v-icon>
                {{ category?.id ? $t('mailings.categories.editTitle') : $t('mailings.categories.newTitle') }}
                <v-spacer />
                <v-btn icon="mdi-close" variant="text" :aria-label="$t('common.close')" @click="open = false" />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <!-- The one nobody may turn off. Not editable from here:
                     an admin who could tick it could make the newsletter
                     unrefusable. -->
                <v-alert
                    v-if="category?.is_mandatory"
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                >
                    {{ $t('mailings.categories.mandatoryHint') }}
                </v-alert>

                <v-row dense>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.icon"
                            :label="$t('mailings.categories.icon')"
                            :prepend-inner-icon="form.icon || 'mdi-email-outline'"
                            variant="outlined"
                            density="comfortable"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.color"
                            :label="$t('mailings.categories.color')"
                            variant="outlined"
                            density="comfortable"
                        />
                    </v-col>
                </v-row>

                <v-switch
                    v-model="form.default_opt_in"
                    color="primary"
                    density="compact"
                    hide-details
                    :label="$t('mailings.categories.defaultOptIn')"
                    :disabled="category?.is_mandatory"
                />
                <div class="text-caption text-medium-emphasis mb-4">
                    {{ form.default_opt_in
                        ? $t('mailings.categories.defaultOnHint')
                        : $t('mailings.categories.defaultOffHint') }}
                </div>

                <v-tabs v-model="locale" density="compact" class="mb-2">
                    <v-tab v-for="code in store.locales" :key="code" :value="code">
                        {{ code.toUpperCase() }}
                        <v-icon v-if="code === store.defaultLocale" size="14" class="ml-1">mdi-asterisk</v-icon>
                    </v-tab>
                </v-tabs>

                <v-window v-model="locale">
                    <v-window-item v-for="code in store.locales" :key="code" :value="code">
                        <v-text-field
                            v-model="form.translations[code].name"
                            :label="$t('mailings.categories.name')"
                            :error-messages="errors[`translations.${code}.name`]"
                            variant="outlined"
                            density="comfortable"
                            class="mt-2"
                        />
                        <v-textarea
                            v-model="form.translations[code].description"
                            :label="$t('mailings.categories.description')"
                            :hint="$t('mailings.categories.descriptionHint')"
                            persistent-hint
                            variant="outlined"
                            density="comfortable"
                            rows="2"
                            auto-grow
                        />
                    </v-window-item>
                </v-window>
            </v-card-text>

            <v-divider />

            <v-card-actions>
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

/**
 * A kind of mail, which is a row on every member's preferences page.
 *
 * The name and the reason are what members read, so both are translated;
 * the key is derived from the name and never shown, because nobody
 * outside the code cares what it is called.
 */
export default {
    name: 'MailCategoryDialog',
    props: {
        modelValue: { type: Boolean, default: false },
        category: { type: Object, default: null },
    },
    emits: ['update:modelValue', 'saved'],
    setup() {
        return { store: useMailingStore() };
    },
    data() {
        return {
            form: { icon: '', color: '', default_opt_in: true, translations: {} },
            locale: null,
            errors: {},
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
            this.locale = this.store.defaultLocale;

            const translations = {};

            for (const code of this.store.locales) {
                translations[code] = {
                    name: this.category?.translations?.[code]?.name || '',
                    description: this.category?.translations?.[code]?.description || '',
                };
            }

            this.form = {
                icon: this.category?.icon || 'mdi-email-outline',
                color: this.category?.color || 'primary',
                default_opt_in: this.category ? this.category.default_opt_in : true,
                translations,
            };
        },

        async save() {
            this.errors = {};
            try {
                const result = await this.store.saveCategory({
                    id: this.category?.id,
                    icon: this.form.icon,
                    color: this.form.color,
                    default_opt_in: this.form.default_opt_in,
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
    },
};
</script>
