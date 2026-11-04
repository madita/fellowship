<template>
    <div class="flex-grow-1">
        <page-header
            :title="$t('mailings.title')"
            :subtitle="$t('mailings.subtitle')"
            icon="mdi-email-multiple-outline"
            :breadcrumbs="[$crumb('admin'), $t('mailings.title')]"
        >
            <template #actions>
                <v-btn
                    variant="tonal"
                    prepend-icon="mdi-refresh"
                    :loading="refreshing"
                    :disabled="refreshing"
                    @click="refresh"
                >
                    {{ $t('common.refresh') }}
                </v-btn>
                <v-btn
                    class="ml-2"
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-email-edit-outline"
                    @click="compose()"
                >
                    {{ $t('mailings.actions.write') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <v-tabs v-model="tab" bg-color="transparent" class="mb-4">
                <v-tab value="mailings">
                    <v-icon start>mdi-email-multiple-outline</v-icon>
                    {{ $t('mailings.tabs.mailings') }}
                </v-tab>
                <v-tab value="templates">
                    <v-icon start>mdi-file-document-multiple-outline</v-icon>
                    {{ $t('mailings.tabs.templates') }}
                </v-tab>
                <v-tab value="categories">
                    <v-icon start>mdi-shape-outline</v-icon>
                    {{ $t('mailings.tabs.categories') }}
                </v-tab>
            </v-tabs>

            <v-window v-model="tab">
                <!-- What has been sent, and what is waiting -->
                <v-window-item value="mailings">
                    <v-card variant="outlined" class="mb-4">
                        <v-card-text class="pa-3">
                            <v-row dense align="center">
                                <v-col cols="12" md="5">
                                    <v-text-field
                                        v-model="search"
                                        :placeholder="$t('mailings.filters.search')"
                                        prepend-inner-icon="mdi-magnify"
                                        density="compact"
                                        hide-details
                                        clearable
                                        variant="outlined"
                                        @update:model-value="onSearchInput"
                                    />
                                </v-col>
                                <v-col cols="12" md="7">
                                    <v-chip-group v-model="status" column>
                                        <v-chip
                                            v-for="option in statusOptions"
                                            :key="option.value ?? 'all'"
                                            :value="option.value"
                                            size="small"
                                            variant="tonal"
                                            filter
                                        >
                                            {{ option.label }}
                                        </v-chip>
                                    </v-chip-group>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>

                    <loading-state v-if="store.loadingMailings && store.mailings.length === 0" />

                    <empty-state
                        v-else-if="store.mailings.length === 0"
                        icon="mdi-email-outline"
                        :title="$t('mailings.emptyTitle')"
                        :text="$t('mailings.emptyText')"
                    />

                    <v-card v-else variant="outlined">
                        <v-list class="pa-0">
                            <template v-for="(mailing, index) in store.mailings" :key="mailing.id">
                                <v-divider v-if="index > 0" />
                                <v-list-item class="py-3">
                                    <template #prepend>
                                        <v-avatar :color="statusMeta(mailing.status).color" variant="tonal" size="40">
                                            <v-icon>{{ statusMeta(mailing.status).icon }}</v-icon>
                                        </v-avatar>
                                    </template>

                                    <v-list-item-title class="font-weight-medium">
                                        {{ mailing.subject }}
                                    </v-list-item-title>

                                    <v-list-item-subtitle class="text-caption">
                                        <v-chip size="x-small" variant="tonal" class="mr-1">
                                            {{ mailing.category?.name }}
                                        </v-chip>
                                        {{ audienceText(mailing) }}
                                        <template v-if="mailing.author"> · {{ mailing.author.username }}</template>
                                        <template v-if="mailing.scheduled_for && mailing.status === 'scheduled'">
                                            · {{ $t('mailings.goesOut', { when: formatDate(mailing.scheduled_for) }) }}
                                        </template>
                                        <template v-else-if="mailing.finished_at">
                                            · {{ formatDateDistanceToNow(mailing.finished_at) }}
                                        </template>
                                        <template v-else>
                                            · {{ formatDateDistanceToNow(mailing.created_at) }}
                                        </template>
                                    </v-list-item-subtitle>

                                    <!-- Only while it means something: a
                                         finished send is a set of counts,
                                         not a bar creeping to the end -->
                                    <v-progress-linear
                                        v-if="mailing.status === 'sending'"
                                        class="mt-2"
                                        :model-value="mailing.progress"
                                        color="primary"
                                        height="4"
                                        rounded
                                    />

                                    <template #append>
                                        <div class="d-flex align-center ga-2">
                                            <div v-if="mailing.recipient_count > 0" class="text-right mr-2">
                                                <div class="text-body-2 font-weight-medium">
                                                    {{ mailing.sent_count }} / {{ mailing.recipient_count }}
                                                </div>
                                                <div class="text-caption text-medium-emphasis">
                                                    {{ $t('mailings.reached') }}
                                                </div>
                                            </div>

                                            <v-chip size="small" :color="statusMeta(mailing.status).color" variant="tonal">
                                                {{ $t(`mailings.status.${mailing.status}`) }}
                                            </v-chip>

                                            <v-btn
                                                icon="mdi-account-multiple-outline"
                                                variant="text"
                                                size="small"
                                                :title="$t('mailings.recipients.title')"
                                                :disabled="mailing.recipient_count === 0"
                                                @click="openRecipients(mailing)"
                                            />
                                            <v-btn
                                                icon="mdi-pencil-outline"
                                                variant="text"
                                                size="small"
                                                :title="$t('common.update')"
                                                @click="compose(mailing)"
                                            />
                                            <v-btn
                                                v-if="['sending', 'scheduled'].includes(mailing.status)"
                                                icon="mdi-stop-circle-outline"
                                                variant="text"
                                                size="small"
                                                color="warning"
                                                :title="$t('mailings.actions.cancel')"
                                                :loading="busy[mailing.id] === 'cancel'"
                                                :disabled="!!busy[mailing.id]"
                                                @click="cancel(mailing)"
                                            />
                                            <v-btn
                                                icon="mdi-delete-outline"
                                                variant="text"
                                                size="small"
                                                color="error"
                                                :title="$t('common.delete')"
                                                :loading="busy[mailing.id] === 'delete'"
                                                :disabled="!!busy[mailing.id]"
                                                @click="remove(mailing)"
                                            />
                                        </div>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-list>
                    </v-card>

                    <div v-if="store.meta.last_page > 1" class="d-flex justify-center mt-4">
                        <v-pagination
                            v-model="page"
                            :length="store.meta.last_page"
                            :total-visible="7"
                            density="comfortable"
                            @update:model-value="loadMailings"
                        />
                    </div>
                </v-window-item>

                <!-- The wording of the mails the site sends by itself -->
                <v-window-item value="templates">
                    <div class="d-flex align-center mb-3">
                        <div class="text-body-2 text-medium-emphasis">{{ $t('mailings.templates.intro') }}</div>
                        <v-spacer />
                        <v-btn
                            variant="tonal"
                            prepend-icon="mdi-plus"
                            @click="editTemplate(null)"
                        >
                            {{ $t('mailings.templates.add') }}
                        </v-btn>
                    </div>

                    <loading-state v-if="store.loadingTemplates && store.templates.length === 0" />

                    <v-card v-else variant="outlined">
                        <v-list class="pa-0">
                            <template v-for="(template, index) in store.templates" :key="template.id">
                                <v-divider v-if="index > 0" />
                                <v-list-item class="py-2">
                                    <template #prepend>
                                        <v-icon :color="template.is_enabled ? 'primary' : 'grey'">
                                            {{ template.category?.icon || 'mdi-email-outline' }}
                                        </v-icon>
                                    </template>

                                    <v-list-item-title class="font-weight-medium">
                                        {{ subjectOf(template) }}
                                    </v-list-item-title>
                                    <v-list-item-subtitle class="text-caption">
                                        <code>{{ template.key }}</code>
                                        · {{ template.category?.name }}
                                        · {{ writtenIn(template) }}
                                    </v-list-item-subtitle>

                                    <template #append>
                                        <div class="d-flex align-center ga-1">
                                            <v-chip
                                                v-if="template.is_system"
                                                size="x-small"
                                                variant="tonal"
                                                color="info"
                                                :title="$t('mailings.templates.systemHint', { key: template.key })"
                                            >
                                                {{ $t('mailings.templates.systemBadge') }}
                                            </v-chip>
                                            <v-chip v-if="!template.is_enabled" size="x-small" variant="tonal" color="warning">
                                                {{ $t('mailings.templates.off') }}
                                            </v-chip>
                                            <v-btn
                                                icon="mdi-email-edit-outline"
                                                variant="text"
                                                size="small"
                                                :title="$t('mailings.templates.writeFrom')"
                                                @click="composeFrom(template)"
                                            />
                                            <v-btn
                                                icon="mdi-pencil-outline"
                                                variant="text"
                                                size="small"
                                                :title="$t('common.update')"
                                                @click="editTemplate(template)"
                                            />
                                            <v-btn
                                                icon="mdi-delete-outline"
                                                variant="text"
                                                size="small"
                                                color="error"
                                                :title="template.is_system ? $t('mailings.templates.systemBadge') : $t('common.delete')"
                                                :disabled="template.is_system || !!busy[`t${template.id}`]"
                                                :loading="busy[`t${template.id}`] === 'delete'"
                                                @click="removeTemplate(template)"
                                            />
                                        </div>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-list>
                    </v-card>
                </v-window-item>

                <!-- The kinds of mail a member can say no to -->
                <v-window-item value="categories">
                    <div class="d-flex align-center mb-3">
                        <div class="text-body-2 text-medium-emphasis">{{ $t('mailings.categories.intro') }}</div>
                        <v-spacer />
                        <v-btn variant="tonal" prepend-icon="mdi-plus" @click="editCategory(null)">
                            {{ $t('mailings.categories.add') }}
                        </v-btn>
                    </div>

                    <loading-state v-if="store.loadingCategories && store.categories.length === 0" />

                    <v-row v-else dense>
                        <v-col v-for="category in store.categories" :key="category.id" cols="12" md="6">
                            <v-card variant="outlined" class="h-100">
                                <v-card-text>
                                    <div class="d-flex align-center mb-2">
                                        <v-icon :color="category.color" class="mr-2">{{ category.icon }}</v-icon>
                                        <span class="text-subtitle-1 font-weight-medium">{{ category.name }}</span>
                                        <v-spacer />
                                        <v-chip
                                            v-if="category.is_mandatory"
                                            size="x-small"
                                            variant="tonal"
                                            color="blue-grey"
                                            :title="$t('mailings.categories.mandatoryHint')"
                                        >
                                            {{ $t('mailings.categories.mandatory') }}
                                        </v-chip>
                                        <v-chip
                                            v-else
                                            size="x-small"
                                            variant="tonal"
                                            :color="category.default_opt_in ? 'success' : 'grey'"
                                        >
                                            {{ category.default_opt_in
                                                ? $t('mailings.categories.onByDefault')
                                                : $t('mailings.categories.offByDefault') }}
                                        </v-chip>
                                    </div>

                                    <div class="text-body-2 text-medium-emphasis">{{ category.description }}</div>

                                    <div class="text-caption text-medium-emphasis mt-2">
                                        {{ $t('mailings.categories.counts', {
                                            templates: category.templates_count ?? 0,
                                            mailings: category.mailings_count ?? 0,
                                        }) }}
                                    </div>
                                </v-card-text>
                                <v-card-actions>
                                    <v-spacer />
                                    <v-btn
                                        variant="text"
                                        size="small"
                                        prepend-icon="mdi-pencil-outline"
                                        @click="editCategory(category)"
                                    >
                                        {{ $t('common.update') }}
                                    </v-btn>
                                    <v-btn
                                        v-if="!category.is_mandatory"
                                        variant="text"
                                        size="small"
                                        color="error"
                                        prepend-icon="mdi-delete-outline"
                                        :loading="busy[`c${category.id}`] === 'delete'"
                                        :disabled="!!busy[`c${category.id}`]"
                                        @click="removeCategory(category)"
                                    >
                                        {{ $t('common.delete') }}
                                    </v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-window-item>
            </v-window>
        </v-container>

        <mailing-composer
            v-model="composerOpen"
            :mailing="editing"
            @saved="onMailingSaved"
            @sent="onMailingSaved"
        />

        <mail-template-dialog
            v-model="templateOpen"
            :template="editingTemplate"
            @saved="store.fetchCategories()"
        />

        <mail-category-dialog
            v-model="categoryOpen"
            :category="editingCategory"
        />

        <mailing-recipients-dialog
            v-model="recipientsOpen"
            :mailing="viewing"
        />
    </div>
</template>

<script>
import { useMailingStore } from '@/store/mailingStore.js';
import { formatDate, formatDateDistanceToNow } from '@/plugins/formatDate.js';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import MailingComposer from '@/components/admin/mailings/MailingComposer.vue';
import MailTemplateDialog from '@/components/admin/mailings/MailTemplateDialog.vue';
import MailCategoryDialog from '@/components/admin/mailings/MailCategoryDialog.vue';
import MailingRecipientsDialog from '@/components/admin/mailings/MailingRecipientsDialog.vue';

const SEARCH_DEBOUNCE_MS = 300;

/**
 * Admin → Mailings: writing to the members, the wording of the mails the
 * site sends by itself, and the kinds of mail somebody can say no to.
 *
 * Three tabs rather than three pages, because they are read together: the
 * category decides which unsubscribe link a mail carries, and the
 * template is where a mailing's wording comes from.
 *
 * Reachable with the manage-mailings permission as well as by admins —
 * whoever writes the newsletter does not need the keys to everything.
 */
export default {
    name: 'MailingManager',
    components: {
        PageHeader,
        EmptyState,
        LoadingState,
        MailingComposer,
        MailTemplateDialog,
        MailCategoryDialog,
        MailingRecipientsDialog,
    },
    setup() {
        return { store: useMailingStore(), formatDate, formatDateDistanceToNow };
    },
    data() {
        return {
            tab: 'mailings',
            page: 1,
            search: '',
            status: null,
            searchTimer: null,
            // mailing/template/category id → what is running on that row
            busy: {},
            composerOpen: false,
            editing: null,
            templateOpen: false,
            editingTemplate: null,
            categoryOpen: false,
            editingCategory: null,
            recipientsOpen: false,
            viewing: null,
            // Set while a send is running, so the progress bar moves
            poller: null,
        };
    },
    computed: {
        refreshing() {
            return this.store.loadingMailings || this.store.loadingTemplates || this.store.loadingCategories;
        },
        statusOptions() {
            return [
                { value: 'draft', label: this.$t('mailings.status.draft') },
                { value: 'scheduled', label: this.$t('mailings.status.scheduled') },
                { value: 'sending', label: this.$t('mailings.status.sending') },
                { value: 'sent', label: this.$t('mailings.status.sent') },
                { value: 'failed', label: this.$t('mailings.status.failed') },
                { value: 'cancelled', label: this.$t('mailings.status.cancelled') },
            ];
        },
        /**
         * A send in progress is the only reason to keep asking the server
         * anything.
         */
        hasRunningSend() {
            return this.store.mailings.some((mailing) => mailing.status === 'sending');
        },
    },
    watch: {
        status() {
            this.page = 1;
            this.loadMailings();
        },
        hasRunningSend(running) {
            running ? this.startPolling() : this.stopPolling();
        },
    },
    async mounted() {
        await this.refresh();
        if (this.hasRunningSend) this.startPolling();
    },
    beforeUnmount() {
        clearTimeout(this.searchTimer);
        this.stopPolling();
    },
    methods: {
        async refresh() {
            try {
                await Promise.all([this.loadMailings(), this.store.fetchTemplates(), this.store.fetchCategories()]);
            } catch (error) {
                this.$dialog.requestError(error);
            }
        },

        async loadMailings() {
            try {
                await this.store.fetchMailings({
                    page: this.page,
                    per_page: 20,
                    ...(this.status ? { status: this.status } : {}),
                    ...(this.search?.trim() ? { search: this.search.trim() } : {}),
                });
            } catch (error) {
                this.$dialog.requestError(error);
            }
        },

        onSearchInput() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                this.page = 1;
                this.loadMailings();
            }, SEARCH_DEBOUNCE_MS);
        },

        /**
         * While a send is running the counts change on the server, so the
         * list is re-read. Stopped the moment nothing is running — a
         * finished page should not keep asking.
         */
        startPolling() {
            if (this.poller) return;
            this.poller = setInterval(() => this.loadMailings(), 5000);
        },

        stopPolling() {
            if (!this.poller) return;
            clearInterval(this.poller);
            this.poller = null;
        },

        statusMeta(status) {
            return {
                draft: { icon: 'mdi-pencil-outline', color: 'grey' },
                scheduled: { icon: 'mdi-clock-outline', color: 'info' },
                sending: { icon: 'mdi-email-sync-outline', color: 'primary' },
                sent: { icon: 'mdi-check-circle-outline', color: 'success' },
                failed: { icon: 'mdi-alert-circle-outline', color: 'error' },
                cancelled: { icon: 'mdi-cancel', color: 'warning' },
            }[status] || { icon: 'mdi-email-outline', color: 'grey' };
        },

        audienceText(mailing) {
            const audience = mailing.audience || {};

            if (audience.type === 'roles') {
                return this.$t('mailings.audience.summary.roles', { roles: (audience.roles || []).join(', ') });
            }

            if (audience.type === 'users') {
                return this.$t('mailings.audience.summary.users', { count: (audience.user_ids || []).length });
            }

            if (audience.type === 'achievement') {
                const achievement = this.store.achievements.find((row) => row.id === audience.achievement_id);
                return this.$t('mailings.audience.summary.achievement', { name: achievement?.name ?? '' });
            }

            return this.$t('mailings.audience.summary.all');
        },

        subjectOf(template) {
            return template.translations?.[this.store.defaultLocale]?.subject
                || Object.values(template.translations || {})[0]?.subject
                || template.key;
        },

        writtenIn(template) {
            const codes = Object.keys(template.translations || {});
            return codes.length
                ? codes.map((code) => code.toUpperCase()).join(', ')
                : this.$t('mailings.templates.noWording');
        },

        compose(mailing = null) {
            this.editing = mailing;
            this.composerOpen = true;
        },

        /**
         * Write a mailing starting from a template. The composer loads
         * the wording itself once it knows which template.
         */
        composeFrom(template) {
            this.editing = {
                mail_category_id: template.mail_category_id,
                mail_template_id: template.id,
                subject: this.subjectOf(template),
                body: template.translations?.[this.store.defaultLocale]?.body || '',
                action_label: template.action_label,
                action_url: template.action_url,
                audience: { type: 'all' },
                status: 'draft',
            };
            this.composerOpen = true;
        },

        onMailingSaved() {
            this.loadMailings();
        },

        openRecipients(mailing) {
            this.viewing = mailing;
            this.recipientsOpen = true;
        },

        async cancel(mailing) {
            const ok = await this.$dialog.confirm({
                title: this.$t('mailings.confirm.cancelTitle'),
                content: this.$t('mailings.confirm.cancel'),
                confirmationText: this.$t('mailings.actions.cancel'),
                color: 'warning',
            });

            if (!ok) return;

            this.busy = { ...this.busy, [mailing.id]: 'cancel' };
            try {
                const result = await this.store.cancel(mailing.id);
                this.$dialog.success(result.message);
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                const { [mailing.id]: _, ...rest } = this.busy;
                this.busy = rest;
            }
        },

        async remove(mailing) {
            const ok = await this.$dialog.confirmDelete(
                this.$t('mailings.confirm.delete', { subject: mailing.subject })
            );

            if (!ok) return;

            this.busy = { ...this.busy, [mailing.id]: 'delete' };
            try {
                const result = await this.store.deleteMailing(mailing.id);
                this.$dialog.success(result.message);
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                const { [mailing.id]: _, ...rest } = this.busy;
                this.busy = rest;
            }
        },

        editTemplate(template) {
            this.editingTemplate = template;
            this.templateOpen = true;
        },

        async removeTemplate(template) {
            const ok = await this.$dialog.confirmDelete(
                this.$t('mailings.confirm.deleteTemplate', { subject: this.subjectOf(template) })
            );

            if (!ok) return;

            const key = `t${template.id}`;
            this.busy = { ...this.busy, [key]: 'delete' };
            try {
                const result = await this.store.deleteTemplate(template.id);
                this.$dialog.success(result.message);
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                const { [key]: _, ...rest } = this.busy;
                this.busy = rest;
            }
        },

        editCategory(category) {
            this.editingCategory = category;
            this.categoryOpen = true;
        },

        async removeCategory(category) {
            const ok = await this.$dialog.confirmDelete(
                this.$t('mailings.confirm.deleteCategory', { name: category.name })
            );

            if (!ok) return;

            const key = `c${category.id}`;
            this.busy = { ...this.busy, [key]: 'delete' };
            try {
                const result = await this.store.deleteCategory(category.id);
                this.$dialog.success(result.message);
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                const { [key]: _, ...rest } = this.busy;
                this.busy = rest;
            }
        },
    },
};
</script>
