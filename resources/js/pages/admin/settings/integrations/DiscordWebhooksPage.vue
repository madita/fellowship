<template>
    <div>
        <page-header
            :title="$t('discord.title')"
            :subtitle="$t('discord.subtitle')"
            icon="mdi-discord"
            :back-to="{ name: 'admin-settings-category', params: { category: 'integrations' } }"
        >
            <template #actions>
                <v-btn color="primary" variant="elevated" prepend-icon="mdi-plus" @click="startCreate">
                    {{ $t('discord.add') }}
                </v-btn>
            </template>
        </page-header>

        <v-container>
            <v-row>
                <!-- What to do in Discord -->
                <v-col cols="12" lg="5">
                    <v-card class="settings-card" elevation="1" rounded="lg">
                        <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                            <v-icon class="mr-2" color="primary">mdi-help-circle-outline</v-icon>
                            {{ $t('discord.setup.title') }}
                        </v-card-title>
                        <v-card-text>
                            <p class="text-body-2 text-medium-emphasis mb-4">{{ $t('discord.setup.intro') }}</p>

                            <ol class="steps text-body-2">
                                <li v-for="step in 5" :key="step" class="mb-2">
                                    {{ $t(`discord.setup.step${step}`) }}
                                </li>
                            </ol>

                            <v-alert type="warning" variant="tonal" density="compact" class="mt-4 text-body-2">
                                {{ $t('discord.setup.secret') }}
                            </v-alert>

                            <v-btn
                                variant="text"
                                size="small"
                                class="mt-3 px-0"
                                append-icon="mdi-open-in-new"
                                href="https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks"
                                target="_blank"
                                rel="noopener"
                            >
                                {{ $t('discord.setup.help') }}
                            </v-btn>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- The channels that get messages -->
                <v-col cols="12" lg="7">
                    <loading-state v-if="loading && !webhooks.length" />

                    <empty-state
                        v-else-if="!webhooks.length"
                        icon="mdi-discord"
                        :title="$t('discord.empty')"
                        :text="$t('discord.emptyHint')"
                    >
                        <template #actions>
                            <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="startCreate">
                                {{ $t('discord.add') }}
                            </v-btn>
                        </template>
                    </empty-state>

                    <v-card
                        v-for="webhook in webhooks"
                        v-else
                        :key="webhook.id"
                        class="settings-card mb-3"
                        elevation="1"
                        rounded="lg"
                    >
                        <v-card-text>
                            <div class="d-flex align-center flex-wrap ga-2 mb-2">
                                <v-icon icon="mdi-discord" :color="webhook.is_active ? 'primary' : 'medium-emphasis'" />
                                <span class="text-subtitle-1 font-weight-medium">{{ webhook.name }}</span>
                                <v-chip v-if="!webhook.is_active" size="x-small" variant="tonal">
                                    {{ $t('discord.inactive') }}
                                </v-chip>
                                <v-spacer />
                                <span class="text-caption text-medium-emphasis">{{ webhook.url_hint }}</span>
                            </div>

                            <div class="d-flex align-center flex-wrap ga-1 mb-3">
                                <v-chip
                                    v-for="event in webhook.events"
                                    :key="event"
                                    size="x-small"
                                    variant="tonal"
                                    color="primary"
                                >
                                    {{ eventLabel(event) }}
                                </v-chip>
                            </div>

                            <div class="text-caption" :class="deliveryClass(webhook)">
                                <v-icon size="small" :icon="deliveryIcon(webhook)" class="mr-1" />
                                {{ deliveryText(webhook) }}
                            </div>
                        </v-card-text>

                        <v-card-actions class="px-4 pb-4">
                            <v-btn
                                variant="tonal"
                                size="small"
                                prepend-icon="mdi-send-outline"
                                :loading="testing === webhook.id"
                                @click="sendTest(webhook)"
                            >
                                {{ $t('discord.test') }}
                            </v-btn>
                            <v-spacer />
                            <v-btn variant="text" size="small" prepend-icon="mdi-pencil-outline" @click="startEdit(webhook)">
                                {{ $t('discord.edit') }}
                            </v-btn>
                            <v-btn
                                variant="text"
                                size="small"
                                color="error"
                                prepend-icon="mdi-delete-outline"
                                :loading="deleting === webhook.id"
                                @click="remove(webhook)"
                            >
                                {{ $t('discord.delete') }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>

        <!-- Add / edit -->
        <v-dialog v-model="dialog" max-width="640" :persistent="saving">
            <v-card>
                <v-card-title class="d-flex align-center ga-2 pt-4 px-6">
                    <v-icon icon="mdi-discord" color="primary" />
                    {{ editing ? $t('discord.editTitle') : $t('discord.addTitle') }}
                </v-card-title>
                <v-card-text class="px-6">
                    <v-form ref="form" @submit.prevent="save">
                        <v-text-field
                            v-model="form.name"
                            :label="$t('discord.fields.name')"
                            :hint="$t('discord.fields.nameHint')"
                            persistent-hint
                            :rules="[v => !!v?.trim() || $t('discord.validation.nameRequired')]"
                            variant="outlined"
                            density="comfortable"
                            class="mb-4"
                        />
                        <v-text-field
                            v-model="form.url"
                            :label="$t('discord.fields.url')"
                            :placeholder="urlPlaceholder"
                            :hint="editing ? $t('discord.fields.urlKeep') : $t('discord.fields.urlHint')"
                            persistent-hint
                            :rules="urlRules"
                            variant="outlined"
                            density="comfortable"
                            autocomplete="off"
                            class="mb-4"
                        />

                        <div class="text-subtitle-2 font-weight-medium mb-1">{{ $t('discord.fields.events') }}</div>
                        <div class="text-caption text-medium-emphasis mb-2">{{ $t('discord.fields.eventsHint') }}</div>
                        <v-checkbox
                            v-for="event in events"
                            :key="event"
                            v-model="form.events"
                            :value="event"
                            :label="eventLabel(event)"
                            :hint="eventHint(event)"
                            persistent-hint
                            density="compact"
                            hide-details="auto"
                            class="mb-1"
                        />
                        <div v-if="eventsError" class="text-caption text-error mt-2">{{ eventsError }}</div>

                        <v-switch
                            v-model="form.is_active"
                            :label="$t('discord.fields.active')"
                            color="primary"
                            density="compact"
                            hide-details
                            class="mt-3"
                        />
                    </v-form>
                </v-card-text>
                <v-card-actions class="px-6 pb-4">
                    <v-spacer />
                    <v-btn variant="text" :disabled="saving" @click="dialog = false">{{ $t('discord.cancel') }}</v-btn>
                    <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('discord.save') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

const emptyForm = () => ({ name: '', url: '', events: [], is_active: true });

/**
 * Discord channels that announce what happens on the site: how to make a
 * webhook in Discord, which events go to which channel, and a test message.
 */
export default {
    name: 'DiscordWebhooksPage',
    components: { PageHeader, EmptyState, LoadingState },
    data() {
        return {
            webhooks: [],
            events: [],
            loading: false,
            dialog: false,
            editing: null,
            saving: false,
            testing: null,
            deleting: null,
            form: emptyForm(),
            eventsError: '',
            urlPlaceholder: 'https://discord.com/api/webhooks/123456789/abcdef…',
        };
    },
    computed: {
        urlRules() {
            const pattern = /^https:\/\/(discord|discordapp)\.com\/api\/webhooks\/\d+\/[\w-]+$/;
            return [
                v => (this.editing && !v) || !!v || this.$t('discord.validation.urlRequired'),
                v => !v || pattern.test(v.trim()) || this.$t('discord.validation.urlInvalid'),
            ];
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        eventLabel(event) {
            return this.$t(`discord.events.${event}.label`);
        },
        eventHint(event) {
            return this.$t(`discord.events.${event}.hint`);
        },
        deliveryIcon(webhook) {
            if (!webhook.last_sent_at) return 'mdi-clock-outline';
            return webhook.last_error ? 'mdi-alert-circle-outline' : 'mdi-check-circle-outline';
        },
        deliveryClass(webhook) {
            if (!webhook.last_sent_at) return 'text-medium-emphasis';
            return webhook.last_error ? 'text-error' : 'text-success';
        },
        deliveryText(webhook) {
            if (!webhook.last_sent_at) return this.$t('discord.delivery.never');
            const when = formatDateDistanceToNow(webhook.last_sent_at);
            return webhook.last_error
                ? this.$t('discord.delivery.failed', { when, error: webhook.last_error })
                : this.$t('discord.delivery.ok', { when });
        },
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/admin/discord-webhooks');
                this.webhooks = data.data;
                this.events = data.events;
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('discord.messages.loadFailed'));
            } finally {
                this.loading = false;
            }
        },
        startCreate() {
            this.editing = null;
            this.form = emptyForm();
            this.eventsError = '';
            this.dialog = true;
        },
        startEdit(webhook) {
            this.editing = webhook;
            // The address is never handed back; leaving it empty keeps it
            this.form = { name: webhook.name, url: '', events: [...webhook.events], is_active: webhook.is_active };
            this.eventsError = '';
            this.dialog = true;
        },
        async save() {
            if (this.saving) return;
            const { valid } = await this.$refs.form.validate();
            this.eventsError = this.form.events.length ? '' : this.$t('discord.validation.eventsRequired');
            if (!valid || this.eventsError) return;

            this.saving = true;
            try {
                if (this.editing) {
                    await axios.patch(`/api/admin/discord-webhooks/${this.editing.id}`, this.form);
                } else {
                    await axios.post('/api/admin/discord-webhooks', this.form);
                }
                this.dialog = false;
                await this.load();
                await this.$dialog.success(this.$t('discord.messages.saved'));
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('discord.messages.saveFailed'));
            } finally {
                this.saving = false;
            }
        },
        async sendTest(webhook) {
            if (this.testing) return;
            this.testing = webhook.id;
            try {
                const { data } = await axios.post(`/api/admin/discord-webhooks/${webhook.id}/test`);
                await this.load();
                if (data.delivered) {
                    await this.$dialog.success(this.$t('discord.messages.testSent', { name: webhook.name }));
                } else {
                    await this.$dialog.error(this.$t('discord.messages.testFailed', { error: data.webhook?.last_error || '' }));
                }
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('discord.messages.testFailed', { error: '' }));
            } finally {
                this.testing = null;
            }
        },
        async remove(webhook) {
            if (this.deleting) return;
            if (!(await this.$dialog.confirmDelete(this.$t('discord.confirmDelete', { name: webhook.name })))) return;

            this.deleting = webhook.id;
            try {
                await axios.delete(`/api/admin/discord-webhooks/${webhook.id}`);
                this.webhooks = this.webhooks.filter(item => item.id !== webhook.id);
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('discord.messages.deleteFailed'));
            } finally {
                this.deleting = null;
            }
        },
    },
};
</script>

<style scoped>
.settings-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.steps {
    padding-left: 18px;
    line-height: 1.6;
}
</style>
